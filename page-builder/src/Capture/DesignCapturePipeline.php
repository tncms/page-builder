<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Capture;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Plugins\PageBuilder\Builder\Registry\BuilderResourceRegistry;
use Plugins\PageBuilder\Capture\Events\DesignResourceCaptured;
use Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface;

/**
 * The ONE reusable Design Capture Pipeline (Phase 26.48).
 *
 * Builder → Selection → [capture → normalize → validate → fingerprint → preview →
 * metadata → persist] → repository refresh → registry refresh. Every future
 * feature (My Designs, Community, Marketplace, PRO, Portable Package) reuses this
 * one pipeline; there is never a second save path.
 *
 * It contains NO UI logic and NO repository query logic (Rule 5): it persists to
 * the UserDesignStore — the ONLY writer into the "my-designs" source (Rule 11) —
 * then triggers the repository + registry to refresh so the new resource is
 * available without reloading the Builder (Rule 12). Saving is an external
 * persistence action: it never touches BuilderState, the CommandBus, or undo
 * history (Rules 13/20).
 */
final class DesignCapturePipeline
{
    /** @var array<int, string> */
    private const SUPPORTED_KINDS = ['section', 'block'];

    private const SOURCE = 'my-designs';

    private const INITIAL_VERSION = '1.0.0';

    public function __construct(
        private readonly DesignCaptureNormalizer $normalizer,
        private readonly DesignCaptureValidator $validator,
        private readonly DesignPreviewGenerator $preview,
        private readonly UserDesignStore $store,
        private readonly DesignResourceRepositoryInterface $repository,
        private readonly BuilderResourceRegistry $registry,
        private readonly Dispatcher $events,
    ) {
    }

    /**
     * Capture a Builder selection into a reusable design resource.
     *
     * @param  array{node?: array<string, mixed>, kind?: string}  $selection
     * @param  array<string, mixed>  $metadata  name/description/category/tags
     */
    public function capture(array $selection, array $metadata, ?string $author, bool $forceCopy = false): DesignCaptureResult
    {
        $kind = (string) ($selection['kind'] ?? '');
        $node = is_array($selection['node'] ?? null) ? $selection['node'] : [];

        if (! in_array($kind, self::SUPPORTED_KINDS, true)) {
            return DesignCaptureResult::invalid([['code' => 'unsupported_root', 'message' => 'Only sections and blocks can be saved as a design.']]);
        }
        if ($node === []) {
            return DesignCaptureResult::invalid([['code' => 'empty', 'message' => 'There is nothing to save.']]);
        }

        // Normalize → validate BEFORE any persistence (Rules 6/7).
        $payload = $this->normalizer->normalize($node, $kind);
        $issues = $this->validator->validate($payload, $kind);
        if ($issues !== []) {
            return DesignCaptureResult::invalid($issues);
        }

        // Deterministic fingerprint of the normalized JSON only (Rule 8).
        $fingerprint = DesignFingerprint::of($payload);

        // Duplicate handling (Rule 9): only Create Copy overrides.
        $existing = $this->store->findByFingerprint($fingerprint);
        if ($existing !== null && ! $forceCopy) {
            return DesignCaptureResult::duplicate($fingerprint, (string) ($existing['rid'] ?? ''));
        }

        $resourceId = $this->newResourceId();
        $rid = self::SOURCE.':'.$kind.':'.$resourceId;
        $now = Carbon::now()->toIso8601String();

        // Metadata (Rule 10) — never any Builder runtime state.
        $record = [
            'rid' => $rid,
            'source' => self::SOURCE,
            'kind' => $kind,
            'resourceId' => $resourceId,
            'name' => $this->name($metadata['name'] ?? null),
            'description' => $this->text($metadata['description'] ?? null),
            'category' => $this->category($metadata['category'] ?? null),
            'tags' => $this->tags($metadata['tags'] ?? []),
            'version' => self::INITIAL_VERSION,
            'author' => $author,
            'compatibility' => ['schemaVersion' => '1'],
            'fingerprint' => $fingerprint,
            'preview' => $this->preview->generate($rid),
            'distribution' => ['entitlement' => 'free'], // inherits availability defaults (Rule 14)
            'payload' => $payload,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        // Persist (the ONLY writer), then refresh so the resource is immediately
        // available in the repository + registry without reloading the Builder.
        $this->store->save($resourceId, $record);
        $this->repository->refresh();
        $this->registry->refresh();

        $this->events->dispatch(new DesignResourceCaptured($rid, $fingerprint, $kind, $author));

        return DesignCaptureResult::saved($rid, $fingerprint);
    }

    private function newResourceId(): string
    {
        // Lowercase ULID → a valid, unique, slug-safe resource id.
        return 'd-'.Str::lower((string) Str::ulid());
    }

    private function name(mixed $value): string
    {
        $name = is_string($value) ? trim($value) : '';

        return $name !== '' ? $name : 'Untitled design';
    }

    private function text(mixed $value): ?string
    {
        $text = is_string($value) ? trim($value) : '';

        return $text !== '' ? $text : null;
    }

    private function category(mixed $value): string
    {
        // Reuse the existing category model (Rule 15) — a lowercase slug, default 'general'.
        $slug = is_string($value) ? Str::slug($value) : '';

        return $slug !== '' ? $slug : 'general';
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function tags(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $tags = [];
        foreach ($value as $tag) {
            if (is_string($tag) && ($slug = Str::slug($tag)) !== '') {
                $tags[$slug] = true;
            }
        }

        return array_keys($tags);
    }
}
