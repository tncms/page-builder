<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns;

use Illuminate\Support\Str;
use Plugins\PageBuilder\Patterns\Dependencies\PatternDependencies;

/**
 * Typed, immutable metadata for a pattern (the manifest.json contents), normalised
 * with sensible defaults. Only id/name/category are strictly required; everything
 * else degrades gracefully so third-party packs need only a minimal manifest.
 */
final class PatternManifest
{
    /**
     * @param  array<int, string>  $tags
     * @param  array<int, string>  $supportedLocales
     * @param  array<int, string>  $requiredComponents
     * @param  array<int, string>  $keywords
     * @param  array<int, PatternVariable>  $variables  Optional insert-time variables (§2).
     * @param  array{type: string, src: string}  $preview  Preview strategy metadata (§8).
     * @param  array<string, mixed>  $metadata  Optional descriptive metadata (§11).
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $category,
        public readonly string $description = '',
        public readonly array $tags = [],
        public readonly string $version = '1.0.0',
        public readonly string $author = '',
        public readonly string $thumbnail = 'preview.webp',
        public readonly array $supportedLocales = [],
        public readonly array $requiredComponents = [],
        public readonly array $keywords = [],
        public readonly string $difficulty = 'beginner',
        public readonly bool $featured = false,
        public readonly bool $premium = false,
        public readonly string $source = '',
        public readonly int $previewWidth = 1280,
        public readonly int $previewHeight = 720,
        public readonly array $variables = [],
        public readonly array $preview = ['type' => 'image', 'src' => ''],
        public readonly int $schema = 1,
        public readonly string $builder = '1.0',
        public readonly array $metadata = [],
        public readonly PatternDependencies $dependencies = new PatternDependencies(),
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $source = ''): self
    {
        $id = trim((string) ($data['id'] ?? ''));
        $name = trim((string) ($data['name'] ?? '')) ?: Str::headline($id);

        return new self(
            id: $id,
            name: $name,
            slug: trim((string) ($data['slug'] ?? '')) ?: Str::slug($id !== '' ? $id : $name),
            category: trim((string) ($data['category'] ?? '')) ?: 'utility',
            description: (string) ($data['description'] ?? ''),
            tags: self::stringList($data['tags'] ?? []),
            version: (string) ($data['version'] ?? '1.0.0'),
            author: (string) ($data['author'] ?? ''),
            thumbnail: trim((string) ($data['thumbnail'] ?? '')) ?: 'preview.webp',
            supportedLocales: self::stringList($data['supportedLocales'] ?? $data['supported_locales'] ?? []),
            requiredComponents: self::stringList($data['requiredComponents'] ?? $data['required_components'] ?? []),
            keywords: self::stringList($data['keywords'] ?? []),
            difficulty: (string) ($data['difficulty'] ?? 'beginner'),
            featured: (bool) ($data['featured'] ?? false),
            premium: (bool) ($data['premium'] ?? false),
            source: $source !== '' ? $source : (string) ($data['source'] ?? ''),
            previewWidth: (int) ($data['previewWidth'] ?? $data['preview_width'] ?? 1280),
            previewHeight: (int) ($data['previewHeight'] ?? $data['preview_height'] ?? 720),
            variables: self::variableList($data['variables'] ?? []),
            preview: self::normalizePreview($data['preview'] ?? [], trim((string) ($data['thumbnail'] ?? '')) ?: 'preview.webp'),
            schema: (int) ($data['schema'] ?? 1),
            builder: (string) ($data['builder'] ?? '1.0'),
            metadata: self::metadata($data),
            dependencies: PatternDependencies::fromArray($data['dependencies'] ?? []),
        );
    }

    /**
     * Parse the optional variable list into typed {@see PatternVariable} objects.
     *
     * @param  mixed  $value
     * @return array<int, PatternVariable>
     */
    private static function variableList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $entry) {
            if (is_array($entry) && trim((string) ($entry['id'] ?? '')) !== '') {
                $out[] = PatternVariable::fromArray($entry);
            }
        }

        return $out;
    }

    /**
     * Normalise the preview strategy block. Falls back to an `image` preview using
     * the thumbnail so patterns predating Phase 25.1 resolve to a valid image (§8).
     * Phase 25.2.x adds optional responsive sources (desktop/tablet/mobile); the base
     * `src` defaults to the desktop source when not given (backward compatible).
     *
     * @param  mixed  $value
     * @return array{type: string, src: string, responsive: array<string, string>}
     */
    private static function normalizePreview(mixed $value, string $thumbnail): array
    {
        $preview = is_array($value) ? $value : [];
        $type = trim((string) ($preview['type'] ?? '')) ?: 'image';

        $responsive = [];
        foreach (['desktop', 'tablet', 'mobile'] as $breakpoint) {
            $source = trim((string) ($preview[$breakpoint] ?? ''));
            if ($source !== '') {
                $responsive[$breakpoint] = $source;
            }
        }

        $src = trim((string) ($preview['src'] ?? ''));
        if ($src === '') {
            $src = $responsive['desktop'] ?? '';
        }
        if ($src === '' && $type === 'image') {
            $src = $thumbnail;
        }

        return ['type' => $type, 'src' => $src, 'responsive' => $responsive];
    }

    /**
     * The optional descriptive metadata (§11). None are required; missing keys are
     * simply absent so third-party packs need only what they use.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function metadata(array $data): array
    {
        $out = [];
        foreach (['created_at', 'updated_at', 'license', 'homepage', 'documentation', 'min_builder_version', 'replacement'] as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($value !== '') {
                $out[$key] = $value;
            }
        }
        if (array_key_exists('deprecated', $data)) {
            $out['deprecated'] = (bool) $data['deprecated'];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'category' => $this->category,
            'description' => $this->description,
            'tags' => $this->tags,
            'version' => $this->version,
            'author' => $this->author,
            'thumbnail' => $this->thumbnail,
            'supportedLocales' => $this->supportedLocales,
            'requiredComponents' => $this->requiredComponents,
            'keywords' => $this->keywords,
            'difficulty' => $this->difficulty,
            'featured' => $this->featured,
            'premium' => $this->premium,
            'source' => $this->source,
            'previewWidth' => $this->previewWidth,
            'previewHeight' => $this->previewHeight,
            'variables' => array_map(static fn (PatternVariable $v): array => $v->toArray(), $this->variables),
            'preview' => $this->preview,
            'schema' => $this->schema,
            'builder' => $this->builder,
            'dependencies' => $this->dependencies->toArray(),
            ...$this->metadata,
        ];
    }

    /** True when the pattern declares insert-time variables (§1/§5). */
    public function hasVariables(): bool
    {
        return $this->variables !== [];
    }

    /** The variable ids, in declaration order. */
    public function variableIds(): array
    {
        return array_map(static fn (PatternVariable $v): string => $v->id, $this->variables);
    }

    /** The declared preview strategy type: image | render | generated (§8). */
    public function previewType(): string
    {
        return (string) ($this->preview['type'] ?? 'image');
    }

    /** The preview source file (relative to the pattern directory), if any. */
    public function previewSrc(): string
    {
        return (string) ($this->preview['src'] ?? '');
    }

    /**
     * Responsive preview sources keyed by breakpoint (Phase 25.2.x, Part 7). Empty
     * when the pattern declares only a single preview image.
     *
     * @return array<string, string>
     */
    public function responsivePreview(): array
    {
        $responsive = $this->preview['responsive'] ?? [];

        return is_array($responsive) ? $responsive : [];
    }

    /**
     * Free-text haystack for search (title, slug, tags, description, keywords,
     * category).
     */
    public function searchText(): string
    {
        return Str::lower(implode(' ', [
            $this->name,
            $this->slug,
            $this->category,
            $this->description,
            implode(' ', $this->tags),
            implode(' ', $this->keywords),
        ]));
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($v): string => is_scalar($v) ? trim((string) $v) : '',
            $value,
        ), static fn (string $v): bool => $v !== ''));
    }
}
