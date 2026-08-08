<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Builder\Palette;

/**
 * One Builder Palette item — a Design Resource projected into palette metadata
 * (Phase 26.47E).
 *
 * Carries only display + insertion metadata. The `rid` is the resource's PUBLIC
 * handle (used to fetch its id-free payload for insertion); no storage paths or
 * package internals are exposed (Rule 10). Availability (Phase 26.45) rides along
 * as `available` / `locked` so the palette can show a locked resource without
 * hiding it.
 */
final class PaletteItem
{
    /**
     * @param  array<int, string>  $tags
     * @param  array<string, mixed>|null  $compatibility
     * @param  array<string, mixed>|null  $preview
     */
    public function __construct(
        public readonly string $rid,
        public readonly string $kind,
        public readonly string $name,
        public readonly string $category,
        public readonly array $tags,
        public readonly string $source,
        public readonly string $group,
        public readonly string $version,
        public readonly ?array $compatibility,
        public readonly ?array $preview,
        public readonly bool $available,
        public readonly bool $locked,
        public readonly ?string $requiredCapability = null,
    ) {
    }

    /**
     * Lowercased haystack used by the unified palette search (name/category/tags/
     * source). Built-in and imported resources search identically (Rule 11).
     */
    public function searchHaystack(): string
    {
        return strtolower(implode(' ', [$this->name, $this->category, $this->source, implode(' ', $this->tags)]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rid' => $this->rid,
            'kind' => $this->kind,
            'name' => $this->name,
            'category' => $this->category,
            'tags' => $this->tags,
            'source' => $this->source,
            'group' => $this->group,
            'version' => $this->version,
            'compatibility' => $this->compatibility,
            'preview' => $this->preview,
            'available' => $this->available,
            'locked' => $this->locked,
            'requiredCapability' => $this->requiredCapability,
        ];
    }
}
