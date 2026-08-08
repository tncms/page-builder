<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns;

use Illuminate\Support\Str;
use Plugins\PageBuilder\Patterns\Packs\PatternPack;
use Plugins\PageBuilder\Patterns\Packs\PatternPackLoader;
use Plugins\PageBuilder\Patterns\Packs\PatternPackRegistry;

/**
 * Owns pattern discovery + lookup (§5). All discovered patterns live here — there
 * is no global pattern state elsewhere. Discovery merges every source; on a
 * duplicate id the highest-priority source wins (theme > plugin packs > core).
 */
final class PatternRegistry
{
    /** @var array<string, Pattern> */
    private array $patterns = [];

    /** @var array<int, array{id: string, source: string, errors: array<int, string>}> */
    private array $invalid = [];

    private bool $discovered = false;

    /**
     * @param  array<string, array<string, mixed>>  $categories  Config category map.
     */
    public function __construct(
        private readonly PatternLoader $loader,
        private readonly PatternValidator $validator,
        private readonly array $categories = [],
        private readonly ?PatternPackLoader $packLoader = null,
        private readonly ?PatternPackRegistry $packRegistry = null,
    ) {}

    /**
     * Discover patterns from the given sources (idempotent per registry instance
     * unless $force). Invalid manifests are skipped and recorded, never registered.
     *
     * @param  array<int, PatternSource>  $sources
     */
    public function discover(array $sources, bool $force = false): self
    {
        if ($this->discovered && ! $force) {
            return $this;
        }

        // Ascending priority so higher-priority sources overwrite on duplicate id.
        usort($sources, static fn (PatternSource $a, PatternSource $b): int => $a->priority <=> $b->priority);

        foreach ($sources as $source) {
            // A source whose root has a pack.json is a pattern pack (Phase 25.2, Part 3);
            // every pattern it contains is tagged with the pack id.
            $packId = $this->resolvePack($source->path);

            foreach ($this->loader->entries($source) as $entry) {
                $errors = $this->validator->validateForDiscovery(dirname($entry['file']), $entry['data'], $this->loader);
                if ($errors !== []) {
                    $this->invalid[] = ['id' => (string) ($entry['data']['id'] ?? '?'), 'source' => $source->id, 'errors' => $errors];

                    continue;
                }
                $pattern = $this->loader->loadOne($entry['file'], $source, $packId);
                if ($pattern !== null) {
                    $this->register($pattern);
                }
            }
        }

        $this->discovered = true;

        return $this;
    }

    /** Register (or override) a pattern. Higher-or-equal priority wins on id clash. */
    public function register(Pattern $pattern): self
    {
        $existing = $this->patterns[$pattern->id()] ?? null;
        if ($existing === null || $pattern->priority() >= $existing->priority()) {
            $this->patterns[$pattern->id()] = $pattern;
        }

        return $this;
    }

    /**
     * @return array<int, Pattern>
     */
    public function all(): array
    {
        return array_values($this->patterns);
    }

    public function find(string $id): ?Pattern
    {
        return $this->patterns[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->patterns[$id]);
    }

    /**
     * @return array<int, Pattern>
     */
    public function featured(): array
    {
        return array_values(array_filter($this->all(), static fn (Pattern $p): bool => $p->manifest()->featured));
    }

    /**
     * @return array<int, Pattern>
     */
    public function search(string $query): array
    {
        $q = Str::lower(trim($query));
        if ($q === '') {
            return $this->all();
        }

        return array_values(array_filter(
            $this->all(),
            static fn (Pattern $p): bool => str_contains($p->manifest()->searchText(), $q),
        ));
    }

    /**
     * Filter by any of: category, tag, featured (bool), premium (bool).
     *
     * @param  array<string, mixed>  $criteria
     * @return array<int, Pattern>
     */
    public function filter(array $criteria): array
    {
        return array_values(array_filter($this->all(), function (Pattern $p) use ($criteria): bool {
            $m = $p->manifest();
            if (isset($criteria['category']) && $criteria['category'] !== '' && $m->category !== $criteria['category']) {
                return false;
            }
            if (isset($criteria['tag']) && $criteria['tag'] !== '' && ! in_array($criteria['tag'], $m->tags, true)) {
                return false;
            }
            if (array_key_exists('featured', $criteria) && $m->featured !== (bool) $criteria['featured']) {
                return false;
            }
            if (array_key_exists('premium', $criteria) && $m->premium !== (bool) $criteria['premium']) {
                return false;
            }

            return true;
        }));
    }

    /**
     * Resolve an ordered id list to patterns (editor-local favorites / recent), in
     * the given order, skipping ids that no longer exist.
     *
     * @param  array<int, string>  $ids
     * @return array<int, Pattern>
     */
    public function byIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $p = $this->find($id);
            if ($p !== null) {
                $out[] = $p;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<int, Pattern>
     */
    public function favorites(array $ids): array
    {
        return $this->byIds($ids);
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<int, Pattern>
     */
    public function recent(array $ids): array
    {
        return $this->byIds($ids);
    }

    /**
     * Categories (from config), ordered, annotated with the pattern count. Only
     * categories that have at least one pattern are returned by default.
     *
     * @return array<int, array<string, mixed>>
     */
    public function categories(bool $withEmpty = false): array
    {
        $counts = [];
        foreach ($this->all() as $p) {
            $counts[$p->manifest()->category] = ($counts[$p->manifest()->category] ?? 0) + 1;
        }

        $out = [];
        foreach ($this->categories as $key => $meta) {
            $count = $counts[$key] ?? 0;
            if (! $withEmpty && $count === 0) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'name' => (string) ($meta['name'] ?? Str::headline($key)),
                'icon' => (string) ($meta['icon'] ?? ''),
                'description' => (string) ($meta['description'] ?? ''),
                'order' => (int) ($meta['order'] ?? 999),
                'count' => $count,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $out;
    }

    /**
     * @return array<int, array{id: string, source: string, errors: array<int, string>}>
     */
    public function invalid(): array
    {
        return $this->invalid;
    }

    public function isDiscovered(): bool
    {
        return $this->discovered;
    }

    /**
     * The discovered pattern packs (Phase 25.2, Part 3), empty when packs are not
     * wired (e.g. unit tests) or none were found.
     *
     * @return array<int, PatternPack>
     */
    public function packs(): array
    {
        return $this->packRegistry?->all() ?? [];
    }

    public function pack(string $id): ?PatternPack
    {
        return $this->packRegistry?->find($id);
    }

    /**
     * Detect + register the pack for a source root, returning its id (or '' when the
     * source is not a pack).
     */
    private function resolvePack(string $path): string
    {
        if ($this->packLoader === null) {
            return '';
        }

        $pack = $this->packLoader->fromDirectory($path);
        if ($pack === null) {
            return '';
        }

        $this->packRegistry?->register($pack);

        return $pack->id;
    }
}
