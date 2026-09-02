<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Provenance;

use Plugins\PageBuilder\Library\Availability\ResourceAvailabilityResolver;
use Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface;
use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\Rid;
use Plugins\PageBuilder\Repository\Theme\ActiveThemeResourceLocator;
use Plugins\PageBuilder\State\BuilderState;
use Plugins\PageBuilder\State\CommandBus;
use Plugins\PageBuilder\State\CommandFactory;
use Plugins\PageBuilder\Support\LayoutNormalizer;

/**
 * Orchestrates inserting a trusted registered resource into the editor's current
 * (unsaved) state and minting provenance from the AUTHORITATIVE command result
 * (PB-TREG-2 mint-at-insert). It is NOT a second insertion authority: every state
 * mutation is performed by dispatching the existing {@see InsertPatternCommand} /
 * {@see InsertComponentCommand} through the one {@see CommandBus}. The browser never
 * supplies the materialized payload, canonical IDs, provider, provenance, or group
 * metadata — this service derives all trusted fields server-side, lets the command
 * regenerate canonical IDs exactly once, extracts the exact inserted roots by an
 * authoritative before/after comparison, and issues the attestation group for those
 * roots. It persists nothing; the save lifecycle remains the only persistence authority.
 */
final class RegisteredResourceInsertion
{
    public function __construct(
        private readonly DesignResourceRepositoryInterface $repository,
        private readonly ResourceAvailabilityResolver $availability,
        private readonly LayoutNormalizer $normalizer,
        private readonly CommandFactory $factory,
        private readonly CommandBus $bus,
        private readonly MaterializationAttestation $attestation,
        private readonly ActiveThemeResourceLocator $themeLocator,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $currentPayload  the client's current unsaved editor state
     * @return array{state: array<string, mixed>, attestations: array<int, string>, rootIds: array<int, string>, expiresAt: int, horizonAt: int}
     *
     * @throws RegisteredResourceInsertionException  atomically, on any failure (no state, no tokens)
     */
    public function insert(string $rid, ?array $currentPayload, AttestationContext $context, ?string $targetParentId, ?int $index): array
    {
        $now = time();
        $resource = $rid !== '' ? $this->repository->tryFind($rid) : null;
        if ($resource === null) {
            throw new RegisteredResourceInsertionException(RegisteredResourceInsertionException::NOT_FOUND);
        }
        if (! in_array($resource->kind, [Rid::KIND_SECTION, Rid::KIND_BLOCK], true)) {
            throw new RegisteredResourceInsertionException(RegisteredResourceInsertionException::UNSUPPORTED_KIND);
        }
        if (! $this->availability->isImportable($resource)) {
            throw new RegisteredResourceInsertionException(RegisteredResourceInsertionException::UNAVAILABLE);
        }

        // Trusted resource payload only — never a client-provided payload.
        $trusted = $this->normalizer->normalize($resource->payload);
        if (($trusted['sections'] ?? []) === []) {
            throw new RegisteredResourceInsertionException(RegisteredResourceInsertionException::EMPTY_RESOURCE);
        }

        $before = BuilderState::make($currentPayload);
        $beforeAllIds = $this->collectIds($before->payload()['sections'] ?? []);

        [$after, $roots] = $resource->kind === Rid::KIND_SECTION
            ? $this->insertPattern($before, $trusted['sections'], $index)
            : $this->insertBlock($before, $trusted['sections'], $targetParentId, $index);

        // Fail closed: every inserted root must be a unique, newly-created, typed node.
        $seen = [];
        foreach ($roots as $root) {
            if ($root['type'] === '' || in_array($root['id'], $beforeAllIds, true) || isset($seen[$root['id']])) {
                throw new RegisteredResourceInsertionException(RegisteredResourceInsertionException::ROOT_EXTRACTION);
            }
            $seen[$root['id']] = true;
        }

        $provenance = LayoutProvenance::fromResource($resource, $this->providerSlug($resource));
        if ($provenance === null) {
            throw new RegisteredResourceInsertionException(RegisteredResourceInsertionException::ATTRIBUTION);
        }

        $tokens = $this->attestation->issueGroup($provenance, $roots, $context, 'mg-'.bin2hex(random_bytes(12)), now: $now);

        return [
            'state' => $after->toArray(),
            'attestations' => $tokens,
            'rootIds' => array_column($roots, 'id'),
            // Safe (non-secret) lifecycle metadata so the transport-only client can decide
            // when to renew WITHOUT parsing the opaque token.
            'expiresAt' => $now + MaterializationAttestation::DEFAULT_TTL,
            'horizonAt' => $now + MaterializationAttestation::DEFAULT_HORIZON,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array{0: BuilderState, 1: array<int, array{id: string, type: string}>}
     */
    private function insertPattern(BuilderState $before, array $sections, ?int $index): array
    {
        $beforeTop = $this->topSectionIds($before->payload()['sections'] ?? []);

        // The one insertion authority regenerates every inserted section id exactly once.
        $after = $this->bus->dispatch($before, $this->factory->make('insert-pattern', [
            'sections' => $sections,
            'index' => $index,
        ]));

        $roots = [];
        foreach ($after->payload()['sections'] ?? [] as $section) {
            $id = is_array($section) ? ($section['id'] ?? null) : null;
            if (is_string($id) && $id !== '' && ! in_array($id, $beforeTop, true)) {
                $roots[] = ['id' => $id, 'type' => (string) ($section['type'] ?? '')];
            }
        }

        // The authoritative result must insert exactly the trusted top-level section count.
        if (count($roots) !== count($sections)) {
            throw new RegisteredResourceInsertionException(RegisteredResourceInsertionException::ROOT_EXTRACTION);
        }

        return [$after, $roots];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array{0: BuilderState, 1: array<int, array{id: string, type: string}>}
     */
    private function insertBlock(BuilderState $before, array $sections, ?string $targetParentId, ?int $index): array
    {
        if ($targetParentId === null) {
            throw new RegisteredResourceInsertionException(RegisteredResourceInsertionException::TARGET_REQUIRED);
        }

        $block = $sections[0]['blocks'][0] ?? null;
        if (! is_array($block)) {
            throw new RegisteredResourceInsertionException(RegisteredResourceInsertionException::EMPTY_RESOURCE);
        }

        $after = $this->bus->dispatch($before, $this->factory->make('insert-component', [
            'component' => $block,
            'target_parent_id' => $targetParentId,
            'index' => $index,
        ]));

        // insert-component selects the just-inserted node: that is the authoritative root.
        $insertedId = $after->selected();
        $node = is_string($insertedId) ? $this->findNode($after->payload()['sections'] ?? [], $insertedId) : null;
        if (! is_string($insertedId) || $node === null) {
            throw new RegisteredResourceInsertionException(RegisteredResourceInsertionException::ROOT_EXTRACTION);
        }

        return [$after, [['id' => $insertedId, 'type' => (string) ($node['type'] ?? '')]]];
    }

    /** Server-authoritative provider slug: the active theme slug for a theme resource. */
    private function providerSlug(DesignResource $resource): string
    {
        return $resource->source === 'theme'
            ? ($this->themeLocator->activeThemeSlug() ?? 'theme')
            : $resource->source;
    }

    /**
     * @param  array<int, mixed>  $sections
     * @return array<int, string>
     */
    private function topSectionIds(array $sections): array
    {
        $ids = [];
        foreach ($sections as $section) {
            $id = is_array($section) ? ($section['id'] ?? null) : null;
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @return array<int, string>
     */
    private function collectIds(array $nodes): array
    {
        $ids = [];
        foreach ($this->walk($nodes) as $node) {
            $id = $node['id'] ?? null;
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @return array<string, mixed>|null
     */
    private function findNode(array $nodes, string $id): ?array
    {
        foreach ($this->walk($nodes) as $node) {
            if (($node['id'] ?? null) === $id) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @param  mixed  $nodes
     * @return \Generator<int, array<string, mixed>>
     */
    private function walk(mixed $nodes): \Generator
    {
        if (! is_array($nodes)) {
            return;
        }
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            yield $node;
            if (is_array($node['blocks'] ?? null)) {
                yield from $this->walk($node['blocks']);
            }
            if (is_array($node['data']['blocks'] ?? null)) {
                yield from $this->walk($node['data']['blocks']);
            }
        }
    }
}
