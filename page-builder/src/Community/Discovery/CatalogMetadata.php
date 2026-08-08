<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Discovery;

use Plugins\PageBuilder\Repository\DesignResource;

/**
 * Additive **catalog metadata** for a Design Resource (Phase 26.50D, Rule 2).
 *
 * Catalog metadata is a presentation-layer projection: it combines identity and
 * classification READ from the resource (rid, kind, source, category, tags, name,
 * description, version, compatibility) with a curation OVERLAY (collections, featured /
 * recommended / new / updated states, sort weight, display metadata). It is additive
 * and immutable — it NEVER modifies the rid, fingerprint, payload, version or manifest
 * identity, and it carries NO payload, so discovery never becomes another repository
 * (Rules 1, 2).
 *
 * The curation overlay is sanitised on construction: any attempt to override
 * identity-bearing fields is rejected by {@see validateCuration()} and ignored here.
 *
 * @see docs/catalog-model.md
 */
final class CatalogMetadata
{
    /** Curated discovery states (Rule 5) — curator-set, never algorithmically ranked. */
    public const STATE_FEATURED = 'featured';

    public const STATE_RECOMMENDED = 'recommended';

    public const STATE_NEW = 'new';

    public const STATE_UPDATED = 'updated';

    public const STATES = [self::STATE_FEATURED, self::STATE_RECOMMENDED, self::STATE_NEW, self::STATE_UPDATED];

    /** Overlay keys that would touch identity and are therefore forbidden (Rule 2). */
    private const FORBIDDEN = ['rid', 'fingerprint', 'payload', 'version', 'manifest', 'source', 'kind', 'category'];

    /**
     * @param  array<int, string>  $tags
     * @param  array<int, string>  $collections  curated collection keys
     * @param  array<string, mixed>  $display  display-only metadata (badge, headline, …)
     * @param  array<string, mixed>  $compatibility  read from the resource; never resolved
     */
    public function __construct(
        public readonly string $rid,
        public readonly string $kind,
        public readonly string $source,
        public readonly string $category,
        public readonly array $tags,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $version,
        public readonly array $collections = [],
        public readonly bool $featured = false,
        public readonly bool $recommended = false,
        public readonly bool $isNew = false,
        public readonly bool $isUpdated = false,
        public readonly int $sortWeight = 0,
        public readonly int $createdAt = 0,
        public readonly int $updatedAt = 0,
        public readonly array $display = [],
        public readonly array $compatibility = [],
    ) {}

    /**
     * Project a resource + its curation overlay into catalog metadata. Identity and
     * classification come from the RESOURCE; only additive presentation attributes are
     * read from the (sanitised) overlay.
     *
     * @param  array<string, mixed>  $curation
     */
    public static function fromResource(DesignResource $resource, array $curation = []): self
    {
        // Silently drop forbidden overlay keys — catalog metadata never mutates identity.
        $safe = array_diff_key($curation, array_flip(self::FORBIDDEN));

        return new self(
            rid: $resource->rid,
            kind: $resource->kind,
            source: $resource->source,
            category: $resource->category,
            tags: $resource->tags,
            name: $resource->name,
            description: $resource->description,
            version: $resource->version,
            collections: self::stringList($safe['collections'] ?? []),
            featured: (bool) ($safe['featured'] ?? false),
            recommended: (bool) ($safe['recommended'] ?? false),
            isNew: (bool) ($safe['new'] ?? false),
            isUpdated: (bool) ($safe['updated'] ?? false),
            sortWeight: (int) ($safe['sort_weight'] ?? 0),
            createdAt: (int) ($safe['created_at'] ?? 0),
            updatedAt: (int) ($safe['updated_at'] ?? 0),
            display: is_array($safe['display'] ?? null) ? $safe['display'] : [],
            compatibility: $resource->compatibility ?? [],
        );
    }

    /**
     * Validate a curation overlay before it is applied (Rule 2). Returns the list of
     * violations; an empty list means the overlay is well-formed. A curation overlay
     * MUST NOT carry identity-bearing fields and its shapes must be correct.
     *
     * @param  array<string, mixed>  $curation
     * @return array<int, string>
     */
    public static function validateCuration(array $curation): array
    {
        $errors = [];

        foreach (self::FORBIDDEN as $key) {
            if (array_key_exists($key, $curation)) {
                $errors[] = "catalog metadata must not set identity-bearing field '{$key}'.";
            }
        }

        if (array_key_exists('collections', $curation) && ! self::isStringList($curation['collections'])) {
            $errors[] = 'collections must be a list of strings.';
        }
        if (array_key_exists('sort_weight', $curation) && ! is_int($curation['sort_weight']) && ! is_numeric($curation['sort_weight'])) {
            $errors[] = 'sort_weight must be an integer.';
        }
        if (array_key_exists('display', $curation) && ! is_array($curation['display'])) {
            $errors[] = 'display must be an array of display-only metadata.';
        }

        return $errors;
    }

    public function hasCollection(string $key): bool
    {
        return in_array($key, $this->collections, true);
    }

    public function inState(string $state): bool
    {
        return match ($state) {
            self::STATE_FEATURED => $this->featured,
            self::STATE_RECOMMENDED => $this->recommended,
            self::STATE_NEW => $this->isNew,
            self::STATE_UPDATED => $this->isUpdated,
            default => false,
        };
    }

    /**
     * Metadata-only search over title / description / category / collections / tags /
     * author (vendor is not carried here; author lives in display when curated). No
     * full-text, AI or semantic search (Rule 7).
     */
    public function matchesSearch(string $needle): bool
    {
        $needle = mb_strtolower(trim($needle));
        if ($needle === '') {
            return true;
        }

        $haystacks = [
            $this->name,
            (string) $this->description,
            $this->category,
            implode(' ', $this->collections),
            implode(' ', $this->tags),
            (string) ($this->display['author'] ?? ''),
        ];

        foreach ($haystacks as $hay) {
            if ($hay !== '' && mb_stripos($hay, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Simple, resolution-free compatibility checks (Rule 9). `"*"` means "any"; matches
     * are literal — no version constraint resolution and no dependency resolution.
     */
    public function supportsTheme(string $theme): bool
    {
        $declared = (string) ($this->compatibility['theme'] ?? '*');

        return $declared === '*' || strcasecmp($declared, $theme) === 0;
    }

    public function supportsBuilder(string $version): bool
    {
        $declared = (string) ($this->compatibility['page_builder'] ?? $this->compatibility['builder'] ?? '*');

        return $declared === '*' || $declared === $version;
    }

    public function requiresDependency(string $dependency): bool
    {
        $plugins = $this->compatibility['plugins'] ?? [];
        if (is_array($plugins) && array_key_exists($dependency, $plugins)) {
            return true;
        }

        foreach (['requiredComponents', 'requiredFeatures', 'extensions'] as $key) {
            $bag = $this->compatibility[$key] ?? [];
            if (is_array($bag) && (in_array($dependency, $bag, true) || array_key_exists($dependency, $bag))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function states(): array
    {
        return array_values(array_filter(self::STATES, fn (string $s): bool => $this->inState($s)));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rid' => $this->rid,
            'kind' => $this->kind,
            'source' => $this->source,
            'category' => $this->category,
            'tags' => $this->tags,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'collections' => $this->collections,
            'featured' => $this->featured,
            'recommended' => $this->recommended,
            'new' => $this->isNew,
            'updated' => $this->isUpdated,
            'sort_weight' => $this->sortWeight,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'display' => $this->display,
            'states' => $this->states(),
        ];
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private static function stringList($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  mixed  $value
     */
    private static function isStringList($value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_string($item)) {
                return false;
            }
        }

        return true;
    }
}
