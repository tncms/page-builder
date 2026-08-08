<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Package;

/**
 * A `.tncms-design` package document (FREE v1 — the single-file profile of the
 * Design Package v2 format).
 *
 * Transport only: it carries a manifest, the full resource bodies, and a checksum.
 * It is NOT a runtime — resources become ordinary {@see \Plugins\PageBuilder\Repository\DesignResource}s
 * served through the repository once imported. Serialisation is canonical so the
 * same resources always produce byte-identical bytes (deterministic export).
 */
final class DesignPackageManifest
{
    public const SCHEMA_VERSION = 2;
    public const TYPE = 'design-package';
    public const GENERATOR = 'tncms-page-builder';
    public const EXTENSION = 'tncms-design';

    /**
     * @param  array<string, mixed>  $metadata   {id, name, source, createdAt}
     * @param  array<int, array<string, mixed>>  $resources  full resource bodies
     */
    public function __construct(
        public readonly int $schemaVersion,
        public readonly string $type,
        public readonly array $metadata,
        public readonly array $resources,
        public readonly string $checksum,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            schemaVersion: is_int($data['schemaVersion'] ?? null) ? $data['schemaVersion'] : 0,
            type: is_string($data['type'] ?? null) ? $data['type'] : '',
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            resources: is_array($data['resources'] ?? null) ? array_values(array_filter($data['resources'], 'is_array')) : [],
            checksum: is_string($data['checksum'] ?? null) ? $data['checksum'] : '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schemaVersion' => $this->schemaVersion,
            'type' => $this->type,
            'generator' => self::GENERATOR,
            'metadata' => $this->metadata,
            'resources' => $this->resources,
            'checksum' => $this->checksum,
        ];
    }

    public function name(): string
    {
        $name = $this->metadata['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : ($this->id() ?: 'Design package');
    }

    public function id(): string
    {
        $id = $this->metadata['id'] ?? null;

        return is_string($id) ? $id : '';
    }

    public function resourceCount(): int
    {
        return count($this->resources);
    }

    /**
     * Whether the stored checksum matches the resources. Integrity gate for import.
     */
    public function checksumMatches(): bool
    {
        return $this->checksum !== '' && hash_equals(self::checksumFor($this->resources), $this->checksum);
    }

    /**
     * Deterministic checksum over the resource bodies (canonical JSON, sha-256).
     *
     * @param  array<int, array<string, mixed>>  $resources
     */
    public static function checksumFor(array $resources): string
    {
        return 'sha256-'.hash('sha256', self::canonicalJson($resources));
    }

    /**
     * Canonical JSON: object keys sorted lexicographically, arrays preserved,
     * slashes/unicode unescaped. The single source of determinism for the package.
     */
    public static function canonicalJson(mixed $value): string
    {
        return (string) json_encode(self::canonicalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        // List: preserve order; map: sort keys.
        $isList = array_is_list($value);
        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = self::canonicalize($v);
        }
        if (! $isList) {
            ksort($out);
        }

        return $out;
    }
}
