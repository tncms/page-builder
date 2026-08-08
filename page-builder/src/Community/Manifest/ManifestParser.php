<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Manifest;

use Plugins\PageBuilder\Community\Manifest\Exceptions\InvalidManifestException;

/**
 * Parses the wire form of a Resource Manifest (JSON / array) into an immutable
 * {@see ResourceManifest} (Phase 26.50A, Rule 2).
 *
 * Parsing is TOTAL and lenient: it always yields a manifest, defaulting missing
 * fields to inert values, so that the {@see ManifestValidator} — not the parser —
 * is the single authority on validity. The parser only refuses input that is not a
 * JSON object at all (malformed), throwing {@see InvalidManifestException}.
 *
 * Parsing is the exact inverse of {@see ManifestSerializer}: parse(serialize(m)) ≡ m.
 */
final class ManifestParser
{
    /**
     * @throws InvalidManifestException when the string is not a JSON object
     */
    public function fromJson(string $json): ResourceManifest
    {
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw InvalidManifestException::notJson(json_last_error_msg());
        }
        if (! is_array($decoded)) {
            throw InvalidManifestException::notAnObject();
        }

        return $this->fromArray($decoded);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function fromArray(array $data): ResourceManifest
    {
        return new ResourceManifest(
            schema: $this->string($data, 'schema', ResourceManifest::SCHEMA),
            rid: $this->string($data, 'rid'),
            fingerprint: $this->string($data, 'fingerprint'),
            resourceType: $this->string($data, 'resource_type'),
            provider: $this->string($data, 'provider', 'community'),
            title: $this->string($data, 'title'),
            description: is_string($data['description'] ?? null) ? $data['description'] : null,
            category: $this->string($data, 'category', 'general'),
            tags: $this->stringList($data['tags'] ?? []),
            version: $this->string($data, 'version', '1.0.0'),
            author: AuthorMetadata::fromArray($this->arr($data, 'author')),
            compatibility: CompatibilityMetadata::fromArray($this->arr($data, 'compatibility')),
            license: LicenseMetadata::fromArray($this->arr($data, 'license')),
            dependencies: $this->dependencies($data['dependencies'] ?? []),
            assets: $this->assets($data['assets'] ?? []),
            preview: PreviewMetadata::fromArray($this->arr($data, 'preview')),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function string(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function arr(array $data, string $key): array
    {
        return is_array($data[$key] ?? null) ? $data[$key] : [];
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function stringList($value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    /**
     * @param  mixed  $value
     * @return array<int, ResourceDependency>
     */
    private function dependencies($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $entry) {
            if (is_array($entry)) {
                $out[] = ResourceDependency::fromArray($entry);
            }
        }

        return $out;
    }

    /**
     * @param  mixed  $value
     * @return array<int, AssetReference>
     */
    private function assets($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $entry) {
            if (is_array($entry)) {
                $out[] = AssetReference::fromArray($entry);
            }
        }

        return $out;
    }
}
