<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Packs;

/**
 * Provenance metadata for an installed pack (Phase 25.2.x, Part 6): where it came
 * from, its version, an integrity checksum, and when it was installed. This is a
 * SEAM for a future install/update flow (marketplace, update server) — nothing here
 * performs installation or integrity enforcement today.
 */
final class InstallationMetadata
{
    public function __construct(
        public readonly string $source = 'local',
        public readonly string $version = '',
        public readonly string $checksum = '',
        public readonly string $installedAt = '',
    ) {}

    /**
     * @param  mixed  $data
     */
    public static function fromArray(mixed $data, string $fallbackVersion = ''): self
    {
        $data = is_array($data) ? $data : [];

        return new self(
            source: trim((string) ($data['source'] ?? '')) ?: 'local',
            version: trim((string) ($data['version'] ?? '')) ?: $fallbackVersion,
            checksum: trim((string) ($data['checksum'] ?? '')),
            installedAt: trim((string) ($data['installed_at'] ?? $data['installedAt'] ?? '')),
        );
    }

    /**
     * @return array{source: string, version: string, checksum: string, installed_at: string}
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'version' => $this->version,
            'checksum' => $this->checksum,
            'installed_at' => $this->installedAt,
        ];
    }
}
