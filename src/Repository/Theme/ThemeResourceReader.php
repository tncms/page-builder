<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository\Theme;

use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\Rid;
use Plugins\PageBuilder\Support\LayoutNormalizer;

/**
 * Parses the active theme's declarative Page Builder resource files into immutable
 * {@see DesignResource} objects (PB-TEMPLATE-REGISTRY-1-H1).
 *
 * It is a narrow adapter over existing authorities: identity through {@see Rid},
 * payload shape through {@see LayoutNormalizer}, and the frozen {@see DesignResource}
 * value object. It owns no persistence and never renders.
 *
 * Security posture: theme resource files are treated as untrusted extension data
 * even when locally installed. Only a fixed whitelist of metadata keys is honoured;
 * unknown keys (e.g. a `view`, `callback`, `class`, or absolute `path`) are simply
 * never interpreted, so no data field can become an executable seam. Bounded file
 * count / size / payload depth / node count reject oversized or hostile inputs, and
 * every failure isolates to the offending file — one malformed resource can never
 * remove a valid theme resource or a built-in provider's resources.
 *
 * Revision is CONTENT-derived (active slug + sorted relative paths + per-file content
 * hash + schema/contract version) — never mtime, creation time, enumeration order,
 * absolute paths, or wall-clock — so the same content copied/extracted with different
 * timestamps yields an identical revision while a content change changes it.
 */
final class ThemeResourceReader
{
    /** The channel source slug for every theme-contributed resource RID. */
    public const SOURCE = 'theme';

    private const SCHEMA_VERSION = '1';
    private const CONTRACT_VERSION = 1;

    private const MAX_FILES = 200;
    private const MAX_BYTES = 262_144; // 256 KiB per file
    private const MAX_DEPTH = 12;
    private const MAX_NODES = 500;

    /** Bounds for optional localized metadata maps (name/description), fail-closed per field. */
    private const MAX_LOCALES = 50;
    private const MAX_LABEL = 2048;

    private const SLUG = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /** A permissive-but-bounded locale-code grammar (e.g. en, vi, en-us, zh-hant). */
    private const LOCALE = '/^[a-z]{2,3}(-[a-z0-9]+)*$/';

    /** Directory kind -> the frozen Rid kind it may contribute. */
    private const KIND_MAP = [
        ActiveThemeResourceLocator::KIND_PATTERNS => Rid::KIND_SECTION,
        ActiveThemeResourceLocator::KIND_TEMPLATES => Rid::KIND_PAGE,
    ];

    /** @var array<int, DesignResource>|null */
    private ?array $resources = null;

    private ?int $revision = null;

    public function __construct(
        private readonly ActiveThemeResourceLocator $locator,
        private readonly LayoutNormalizer $normalizer,
    ) {
    }

    /**
     * @return array<int, DesignResource>
     */
    public function all(): array
    {
        $this->load();

        return $this->resources ?? [];
    }

    public function find(string $rid): ?DesignResource
    {
        foreach ($this->all() as $resource) {
            if ($resource->rid === $rid) {
                return $resource;
            }
        }

        return null;
    }

    public function revision(): int
    {
        $this->load();

        return $this->revision ?? 0;
    }

    /**
     * Whether the active theme currently exposes a Page Builder resource root at all.
     * Used by the provider's availability check (no active theme => contributes nothing).
     */
    public function hasActiveThemeResources(): bool
    {
        return $this->locator->resourceRoot() !== null;
    }

    private function load(): void
    {
        if ($this->resources !== null) {
            return;
        }

        $resources = [];
        $signature = [
            'slug='.($this->locator->activeThemeSlug() ?? ''),
            'schema='.self::SCHEMA_VERSION,
            'contract='.self::CONTRACT_VERSION,
        ];

        $count = 0;
        foreach (self::KIND_MAP as $dirKind => $ridKind) {
            foreach ($this->locator->files($dirKind) as $file) {
                if ($count >= self::MAX_FILES) {
                    break 2;
                }
                $count++;

                $raw = @file_get_contents($file['absolute'], false, null, 0, self::MAX_BYTES + 1);
                if ($raw === false || strlen($raw) > self::MAX_BYTES) {
                    continue;
                }

                // Content-derived signature: relative path + content hash only.
                $signature[] = $file['relative'].'='.hash('sha256', $raw);

                $resource = $this->toResource($ridKind, $raw);
                if ($resource !== null) {
                    // Later files never override an earlier RID within this provider.
                    $resources[$resource->rid] ??= $resource;
                }
            }
        }

        sort($signature, SORT_STRING);
        $this->revision = (int) (crc32(implode("\n", $signature)) & 0x7fff_ffff);
        $this->resources = array_values($resources);
    }

    /**
     * Build one immutable resource from a raw file body, or null when it fails any
     * validation gate (fail-closed, isolated).
     */
    private function toResource(string $ridKind, string $raw): ?DesignResource
    {
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return null;
        }

        $id = $data['id'] ?? null;
        if (! is_string($id) || ! preg_match(self::SLUG, $id)) {
            return null;
        }

        // A declared kind, when present, must match the directory-derived kind.
        $declaredKind = $data['kind'] ?? null;
        if (is_string($declaredKind) && $declaredKind !== $ridKind) {
            return null;
        }

        $rawPayload = is_array($data['payload'] ?? null) ? $data['payload'] : null;
        if ($rawPayload === null && is_array($data['sections'] ?? null)) {
            $rawPayload = ['sections' => $data['sections']];
        }
        if ($rawPayload === null) {
            return null;
        }
        if ($this->depth($rawPayload) > self::MAX_DEPTH || $this->nodeCount($rawPayload) > self::MAX_NODES) {
            return null;
        }

        // Reuse the canonical shape authority; drops malformed/typeless nodes and
        // discards unknown keys, so nothing executable survives into the payload.
        $payload = $this->normalizer->normalize($rawPayload);
        if ($payload['sections'] === []) {
            return null;
        }

        try {
            $rid = new Rid(self::SOURCE, $ridKind, $id);
        } catch (\Throwable) {
            return null;
        }

        // Human presentation fields (name, description) may be declared either as a legacy
        // single string OR as a localized {locale: string} map — resolved by Admin UI locale
        // downstream, never here. Machine fields below stay invariant.
        [$name, $nameMap] = $this->humanField($data['name'] ?? null, $id);
        [$description, $descriptionMap] = $this->humanField($data['description'] ?? null, null);

        $localized = [];
        if ($nameMap !== null) {
            $localized['name'] = $nameMap;
        }
        if ($descriptionMap !== null) {
            $localized['description'] = $descriptionMap;
        }

        return new DesignResource(
            rid: $rid->toString(),
            source: self::SOURCE,
            kind: $ridKind,
            resourceId: $id,
            version: is_string($data['version'] ?? null) && $data['version'] !== '' ? $data['version'] : '1.0.0',
            name: $name,
            category: is_string($data['category'] ?? null) && $data['category'] !== '' ? $data['category'] : 'theme',
            tags: $this->stringList($data['tags'] ?? []),
            payload: $payload,
            vendor: is_string($data['vendor'] ?? null) ? $data['vendor'] : null,
            description: $description,
            preview: $this->safePreview($data['preview'] ?? null),
            compatibility: $this->compatibility($data['compatibility'] ?? null),
            distribution: ['entitlement' => 'free'],
            package: ['source' => self::SOURCE],
            localized: $localized === [] ? null : $localized,
        );
    }

    /**
     * Resolve one human presentation field to [invariant, localizedMap]:
     *   - a legacy non-empty string  -> [string, null] (unchanged behaviour);
     *   - a valid {locale: string} map -> [first valid value, map] so identity/legacy paths that
     *     read the invariant field still get a real label;
     *   - anything else -> [$default, null] ($id for name, null for description).
     *
     * @param  mixed  $value
     * @return array{0: string|null, 1: array<string, string>|null}
     */
    private function humanField(mixed $value, ?string $default): array
    {
        if (is_string($value) && $value !== '') {
            return [$value, null];
        }

        $map = $this->localizedMap($value);
        if ($map !== null) {
            return [(string) reset($map), $map];
        }

        return [$default, null];
    }

    /**
     * Validate an optional localized metadata map into a bounded, lowercased {locale: string}
     * shape, or null when it is absent/empty/invalid. Untrusted theme data: only well-formed
     * locale codes and non-empty, length-bounded string values survive; every other entry is
     * dropped rather than interpreted, so no key/value can become an executable seam and one bad
     * entry never poisons the whole field.
     *
     * @param  mixed  $value
     * @return array<string, string>|null
     */
    private function localizedMap(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $map = [];
        foreach ($value as $code => $text) {
            if (count($map) >= self::MAX_LOCALES) {
                break;
            }
            if (! is_string($code)) {
                continue;
            }
            $code = strtolower(trim($code));
            if (! preg_match(self::LOCALE, $code)) {
                continue;
            }
            if (! is_string($text) || $text === '' || strlen($text) > self::MAX_LABEL) {
                continue;
            }
            $map[$code] ??= $text;
        }

        return $map === [] ? null : $map;
    }

    /**
     * Only a safe root-relative thumbnail string is honoured; anything remote,
     * absolute-with-scheme, traversing, or non-string degrades to sample content.
     *
     * @return array<string, mixed>
     */
    private function safePreview(mixed $preview): array
    {
        if (is_array($preview)) {
            $thumb = $preview['thumbnail'] ?? null;
            if (
                is_string($thumb)
                && str_starts_with($thumb, '/')
                && ! str_contains($thumb, '://')
                && ! str_contains($thumb, '..')
                && ! str_contains($thumb, '\\')
            ) {
                return ['thumbnail' => $thumb];
            }
        }

        return ['sampleContent' => true];
    }

    /**
     * Carry the resource's declared compatibility so the existing availability
     * authority can evaluate it. Only safe string lists survive; the schema version
     * is always Page Builder-owned, never taken from the (untrusted) file.
     *
     * @return array<string, mixed>
     */
    private function compatibility(mixed $declared): array
    {
        $compatibility = ['schemaVersion' => self::SCHEMA_VERSION];

        if (is_array($declared)) {
            $required = $this->stringList($declared['requiredComponents'] ?? []);
            if ($required !== []) {
                $compatibility['requiredComponents'] = $required;
            }
            $features = $this->stringList($declared['requiredFeatures'] ?? []);
            if ($features !== []) {
                $compatibility['requiredFeatures'] = $features;
            }
        }

        return $compatibility;
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
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

    private function depth(mixed $value, int $current = 1): int
    {
        if (! is_array($value) || $value === []) {
            return $current;
        }

        $max = $current;
        foreach ($value as $child) {
            if (is_array($child)) {
                $max = max($max, $this->depth($child, $current + 1));
            }
        }

        return $max;
    }

    private function nodeCount(mixed $value): int
    {
        if (! is_array($value)) {
            return 0;
        }

        $count = 0;
        foreach ($value as $child) {
            if (is_array($child)) {
                $count += 1 + $this->nodeCount($child);
            }
        }

        return $count;
    }
}
