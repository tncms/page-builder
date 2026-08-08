<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Publishing;

use Plugins\PageBuilder\Community\Manifest\ResourceManifest;

/**
 * Immutable package metadata generated at publish time (Phase 26.50B, Rule 6).
 *
 * It records WHAT was published — never a download bundle (metadata only). The
 * {@see $packageId} is deterministic (derived from rid + version + fingerprint), so
 * the same published content always yields the same id. Publish time is the only
 * non-deterministic input and is supplied by the caller's clock.
 *
 * @see docs/publishing-history.md
 */
final class PackageMetadata
{
    public const SCHEMA = '1.0';

    public function __construct(
        public readonly string $packageId,
        public readonly string $rid,
        public readonly string $provider,
        public readonly string $publishedVersion,
        public readonly string $manifestVersion,
        public readonly string $resourceFingerprint,
        public readonly int $publishedAt,
        public readonly string $schemaVersion = self::SCHEMA,
    ) {}

    public static function forManifest(ResourceManifest $manifest, int $publishedAt): self
    {
        return new self(
            packageId: self::deriveId($manifest->rid, $manifest->version, $manifest->fingerprint),
            rid: $manifest->rid,
            provider: $manifest->provider,
            publishedVersion: $manifest->version,
            manifestVersion: $manifest->schema,
            resourceFingerprint: $manifest->fingerprint,
            publishedAt: $publishedAt,
        );
    }

    /**
     * A deterministic, collision-resistant package id.
     */
    public static function deriveId(string $rid, string $version, string $fingerprint): string
    {
        return 'pkg_'.substr(hash('sha256', $rid.'@'.$version.'#'.$fingerprint), 0, 32);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            packageId: (string) ($data['package_id'] ?? ''),
            rid: (string) ($data['rid'] ?? ''),
            provider: (string) ($data['provider'] ?? ''),
            publishedVersion: (string) ($data['published_version'] ?? ''),
            manifestVersion: (string) ($data['manifest_version'] ?? ''),
            resourceFingerprint: (string) ($data['resource_fingerprint'] ?? ''),
            publishedAt: (int) ($data['published_at'] ?? 0),
            schemaVersion: (string) ($data['schema_version'] ?? self::SCHEMA),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'package_id' => $this->packageId,
            'rid' => $this->rid,
            'provider' => $this->provider,
            'published_version' => $this->publishedVersion,
            'manifest_version' => $this->manifestVersion,
            'resource_fingerprint' => $this->resourceFingerprint,
            'published_at' => $this->publishedAt,
            'schema_version' => $this->schemaVersion,
        ];
    }
}
