<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Availability;

use Plugins\PageBuilder\Components\ComponentAvailability;
use Plugins\PageBuilder\Components\ComponentProviderRegistry;
use Plugins\PageBuilder\DataSources\DataSourceConfigResolution;
use Plugins\PageBuilder\DataSources\DataSourceConfigValidator;
use Plugins\PageBuilder\DataSources\DataSourceFields;
use Plugins\PageBuilder\Registries\BlockRegistry;
use Plugins\PageBuilder\Registries\SectionRegistry;
use Plugins\PageBuilder\Support\LayoutNormalizer;

/**
 * PB-XREG-4-R1 — server authority for editing a layout while a provider is unavailable.
 *
 * A node whose namespaced type is truthfully UNAVAILABLE (the registry's own classification, never a
 * client flag) is an opaque frozen subtree. Its baseline is the persisted draft of the SAME owner row
 * whose version token the caller has just matched (the in-transaction CAS then guarantees the row is
 * unchanged at write time). A draft is accepted only when:
 *
 *   1. no node has an UNKNOWN type (fail closed — never preserved, never introduced);
 *   2. every UNAVAILABLE node is a frozen root of the baseline, matched by id, exactly once;
 *   3. it keeps the same parent, and its order against every persisted sibling still present;
 *   4. its subtree (type, id, data incl. future keys, children and their order) is canonically equal;
 *   5. no baseline frozen root is missing or turned into another type.
 *
 * Accepted frozen roots are then replaced by their RAW persisted node, so their stored bytes never
 * change. Nodes are never normalized through a component schema, providers are never called, data
 * sources never fetched, views never resolved.
 */
final class UnavailableSubtreeGuard
{
    private const MAX_DEPTH = 32;

    private const ROOT = '#root';

    /** Key prefix for an id-less parent: unique per walk step, never equal across two payloads. */
    private const ANONYMOUS = '#anonymous:';

    private int $anonymous = 0;

    public function __construct(
        private readonly ComponentProviderRegistry $components,
        private readonly LayoutNormalizer $normalizer,
        private readonly SectionRegistry $sections,
        private readonly BlockRegistry $blocks,
        private readonly DataSourceConfigValidator $sources,
    ) {}

    /**
     * Validate a normalized incoming draft against the persisted (raw) draft.
     *
     * $freezeUnknown is for SERVER-INTERNAL writers only (translation sync, CLI repair): their input is
     * derived from persisted state, so a persisted UNKNOWN node is preserved exactly like an unavailable
     * one instead of being refused. Client-authored saves keep the default: UNKNOWN fails closed.
     *
     * @param  array<string, mixed>|null  $persisted
     * @param  array<string, mixed>  $incoming
     *
     * @throws UnavailableSubtreeViolation
     */
    public function assertPreserved(?array $persisted, array $incoming, bool $freezeUnknown = false): void
    {
        $frozen = $this->frozenRoots($persisted, $freezeUnknown);
        $seen = [];
        $incomingSiblings = [];

        $this->walk($incoming['sections'] ?? [], self::ROOT, 'section', 0, function (array $node, string $parent, string $kind) use ($frozen, &$seen, $freezeUnknown): bool {
            $id = $this->idOf($node);
            $availability = $this->components->availability((string) ($node['type'] ?? ''));

            if ($availability === ComponentAvailability::UNKNOWN && ! $freezeUnknown) {
                throw new UnavailableSubtreeViolation(UnavailableSubtreeViolation::UNKNOWN_TYPE, (string) $id);
            }

            if ($availability === ComponentAvailability::KNOWN) {
                if ($id !== null && isset($frozen[$id])) {
                    throw new UnavailableSubtreeViolation(UnavailableSubtreeViolation::FROZEN_CHANGED, $id);
                }

                return true; // descend
            }

            if ($id === null || ! isset($frozen[$id])) {
                throw new UnavailableSubtreeViolation(UnavailableSubtreeViolation::FROZEN_FORGED, (string) $id);
            }
            if (isset($seen[$id])) {
                throw new UnavailableSubtreeViolation(UnavailableSubtreeViolation::FROZEN_DUPLICATED, $id);
            }
            $seen[$id] = true;

            if (trim((string) ($frozen[$id]['node']['type'] ?? '')) !== trim((string) ($node['type'] ?? ''))) {
                throw new UnavailableSubtreeViolation(UnavailableSubtreeViolation::FROZEN_FORGED, $id); // identity, not content
            }
            if ($frozen[$id]['parent'] !== $parent || $frozen[$id]['kind'] !== $kind) {
                throw new UnavailableSubtreeViolation(UnavailableSubtreeViolation::FROZEN_MOVED, $id);
            }
            if ($this->canonical($this->normalizeNode($frozen[$id]['node'], $kind)) !== $this->canonical($node)) {
                throw new UnavailableSubtreeViolation(UnavailableSubtreeViolation::FROZEN_CHANGED, $id);
            }

            return false; // opaque: never descend into a frozen subtree
        }, $incomingSiblings);

        if ($frozen !== []) {
            $this->assertUniqueIds($incomingSiblings);
        }

        foreach ($frozen as $id => $root) {
            if (! isset($seen[$id])) {
                throw new UnavailableSubtreeViolation(UnavailableSubtreeViolation::FROZEN_REMOVED, (string) $id);
            }
            $this->assertOrder((string) $id, $root['siblings'], $incomingSiblings[$root['parent']] ?? []);
        }
    }

    /**
     * Put back the RAW persisted bytes of every frozen root (after {@see assertPreserved()} accepted the
     * draft), so key order, number representation, future keys and attached meta are exactly kept.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $persisted
     * @return array<string, mixed>
     */
    public function restore(array $payload, ?array $persisted, bool $freezeUnknown = false): array
    {
        $frozen = $this->frozenRoots($persisted, $freezeUnknown);
        if ($frozen === []) {
            return $payload;
        }

        $payload['sections'] = $this->restoreNodes($payload['sections'] ?? [], $frozen, 'section', 0);

        return $payload;
    }

    /**
     * The node ids that make a draft unpublishable right now: unavailable components, unknown
     * components, and KNOWN components whose data-source configuration points at an unavailable source.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array{components: list<string>, unknown: list<string>, sources: list<string>}
     */
    public function dependencies(?array $payload): array
    {
        $out = ['components' => [], 'unknown' => [], 'sources' => []];
        $siblings = [];

        try {
            $this->walk($payload['sections'] ?? [], self::ROOT, 'section', 0, function (array $node, string $parent, string $kind) use (&$out): bool {
                $type = (string) ($node['type'] ?? '');
                $id = (string) ($this->idOf($node) ?? '');
                $availability = $this->components->availability($type);

                if ($availability !== ComponentAvailability::KNOWN) {
                    $out[$availability === ComponentAvailability::UNKNOWN ? 'unknown' : 'components'][] = $id;

                    return false;
                }

                $definition = $kind === 'section' ? $this->sections->get($type) : $this->blocks->get($type);
                $data = is_array($node['data'] ?? null) ? $node['data'] : [];
                foreach (DataSourceFields::in($definition) as $name => $accepts) {
                    if ($this->sources->inspect($data[$name] ?? null, $accepts)->state === DataSourceConfigResolution::STATE_UNAVAILABLE) {
                        $out['sources'][] = $id;
                    }
                }

                return true;
            }, $siblings);
        } catch (UnavailableSubtreeViolation) {
            $out['unknown'][] = ''; // too deep to audit: never publishable
        }

        return $out;
    }

    /** @param array{components: list<string>, unknown: list<string>, sources: list<string>} $dependencies */
    public static function blocksPublication(array $dependencies): bool
    {
        return $dependencies['components'] !== [] || $dependencies['unknown'] !== [] || $dependencies['sources'] !== [];
    }

    // ---- internals -------------------------------------------------------------------------------------

    /**
     * Frozen roots of the persisted draft: top-most UNAVAILABLE nodes, keyed by id.
     *
     * @param  array<string, mixed>|null  $persisted
     * @return array<string, array{node: array<string, mixed>, parent: string, kind: string, siblings: list<?string>}>
     */
    private function frozenRoots(?array $persisted, bool $freezeUnknown = false): array
    {
        $roots = [];
        $siblings = [];

        $this->walk($persisted['sections'] ?? [], self::ROOT, 'section', 0, function (array $node, string $parent, string $kind) use (&$roots, $freezeUnknown): bool {
            $availability = $this->components->availability((string) ($node['type'] ?? ''));
            if ($availability === ComponentAvailability::KNOWN || ($availability === ComponentAvailability::UNKNOWN && ! $freezeUnknown)) {
                return true;
            }

            $id = $this->idOf($node);
            // A frozen node must be addressable: a stable unique id under an addressable parent.
            if ($id === null || isset($roots[$id]) || str_starts_with($parent, self::ANONYMOUS)) {
                throw new UnavailableSubtreeViolation(UnavailableSubtreeViolation::FROZEN_UNTRACKED, (string) $id);
            }
            $roots[$id] = ['node' => $node, 'parent' => $parent, 'kind' => $kind, 'siblings' => []];

            return false;
        }, $siblings);

        if ($roots !== []) {
            $this->assertUniqueIds($siblings);
        }

        foreach ($roots as $id => $root) {
            $roots[$id]['siblings'] = $siblings[$root['parent']] ?? [];
        }

        return $roots;
    }

    /**
     * Depth-first walk in normalizer shape (sections[].blocks[], nested data.blocks). $visit returns
     * whether to descend. Records each parent's ordered child ids. A parent without a stable id cannot
     * anchor a frozen child, so its children are keyed to a position-free marker that never matches.
     *
     * @param  callable(array<string, mixed>, string, string): bool  $visit
     * @param  array<string, list<?string>>  $siblings
     */
    private function walk(mixed $nodes, string $parent, string $kind, int $depth, callable $visit, array &$siblings): void
    {
        if (! is_array($nodes)) {
            return;
        }
        if ($depth > self::MAX_DEPTH) {
            throw new UnavailableSubtreeViolation(UnavailableSubtreeViolation::FROZEN_UNTRACKED);
        }

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            $id = $this->idOf($node);
            $siblings[$parent][] = $id;

            if (! $visit($node, $parent, $kind)) {
                continue;
            }

            $key = $id ?? self::ANONYMOUS.(++$this->anonymous);
            $children = $kind === 'section' ? ($node['blocks'] ?? null) : ($node['data']['blocks'] ?? null);
            $this->walk($children, $key, 'block', $depth + 1, $visit, $siblings);
        }
    }

    /**
     * Relative order of the frozen node against each persisted sibling still in the same parent.
     *
     * @param  list<?string>  $before
     * @param  list<?string>  $after
     */
    private function assertOrder(string $id, array $before, array $after): void
    {
        if (in_array(null, $after, true)) {
            throw new UnavailableSubtreeViolation(UnavailableSubtreeViolation::FROZEN_UNTRACKED, $id);
        }
        $from = array_search($id, $before, true);
        $to = array_search($id, $after, true);

        foreach ($before as $index => $sibling) {
            if ($sibling === null || $sibling === $id) {
                continue;
            }
            $now = array_search($sibling, $after, true);
            if ($now === false) {
                continue; // removed or moved elsewhere: no ordering claim
            }
            if (($index < $from) !== ($now < $to)) {
                throw new UnavailableSubtreeViolation(UnavailableSubtreeViolation::FROZEN_REORDERED, $id);
            }
        }
    }

    /**
     * @param  array<string, array{node: array<string, mixed>, parent: string, kind: string, siblings: list<?string>}>  $frozen
     */
    private function restoreNodes(mixed $nodes, array $frozen, string $kind, int $depth): mixed
    {
        if (! is_array($nodes) || $depth > self::MAX_DEPTH) {
            return $nodes;
        }

        foreach ($nodes as $index => $node) {
            if (! is_array($node)) {
                continue;
            }
            $id = $this->idOf($node);
            if ($id !== null && isset($frozen[$id])) {
                $nodes[$index] = $frozen[$id]['node'];

                continue;
            }
            if ($kind === 'section' && is_array($node['blocks'] ?? null)) {
                $nodes[$index]['blocks'] = $this->restoreNodes($node['blocks'], $frozen, 'block', $depth + 1);
            }
            if ($kind === 'block' && is_array($node['data']['blocks'] ?? null)) {
                $nodes[$index]['data']['blocks'] = $this->restoreNodes($node['data']['blocks'], $frozen, 'block', $depth + 1);
            }
        }

        return $nodes;
    }

    /**
     * The persisted node through the SAME layout normalizer the incoming draft went through (shape only:
     * no component schema is applied), so both sides are compared in one representation.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function normalizeNode(array $node, string $kind): array
    {
        if ($kind === 'section') {
            return $this->normalizer->normalize(['sections' => [$node]])['sections'][0] ?? [];
        }

        return $this->normalizer->normalize(['sections' => [['type' => 'section', 'blocks' => [$node]]]])['sections'][0]['blocks'][0] ?? [];
    }

    /** Deterministic structural encoding: map keys sorted, list order kept, integral floats as ints. */
    private function canonical(mixed $value): string
    {
        try {
            return json_encode($this->canonicalValue($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new UnavailableSubtreeViolation(UnavailableSubtreeViolation::FROZEN_CHANGED);
        }
    }

    private function canonicalValue(mixed $value): mixed
    {
        if (is_float($value) && is_finite($value) && floor($value) === $value && abs($value) < 2 ** 53) {
            return (int) $value;
        }
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalValue($item), $value);
        }
        ksort($value, SORT_STRING);

        return (object) array_map(fn (mixed $item): mixed => $this->canonicalValue($item), $value);
    }

    /**
     * Every addressed node id appears once (ids anchor frozen parents and sibling order).
     *
     * @param  array<string, list<?string>>  $siblings
     */
    private function assertUniqueIds(array $siblings): void
    {
        $count = [];
        foreach ($siblings as $ids) {
            foreach ($ids as $id) {
                if ($id === null) {
                    continue;
                }
                if (isset($count[$id])) {
                    throw new UnavailableSubtreeViolation(UnavailableSubtreeViolation::FROZEN_UNTRACKED, $id);
                }
                $count[$id] = true;
            }
        }
    }

    /** @param array<string, mixed> $node */
    private function idOf(array $node): ?string
    {
        $id = $node['id'] ?? null;
        if (is_int($id)) {
            return (string) $id;
        }

        return is_string($id) && trim($id) !== '' ? trim($id) : null;
    }
}
