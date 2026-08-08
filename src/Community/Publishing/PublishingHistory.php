<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Publishing;

/**
 * The immutable, append-only publishing history of a Community Resource (Phase
 * 26.50B, Rule 11).
 *
 * Each publish records one entry: version, fingerprint, published time, manifest
 * version, status and package id. History is APPEND-ONLY — there is no rollback and
 * no branching (Rule 11). {@see record()} returns a NEW history; the underlying
 * entries are never mutated.
 *
 * @see docs/publishing-history.md
 */
final class PublishingHistory
{
    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    public function __construct(
        private readonly array $entries = [],
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    public static function fromArray(array $entries): self
    {
        $clean = [];
        foreach ($entries as $entry) {
            if (is_array($entry)) {
                $clean[] = $entry;
            }
        }

        return new self($clean);
    }

    /**
     * Append a publish entry, returning a NEW history (the receiver is unchanged).
     */
    public function record(PackageMetadata $package): self
    {
        $entry = [
            'version' => $package->publishedVersion,
            'fingerprint' => $package->resourceFingerprint,
            'published_time' => $package->publishedAt,
            'manifest_version' => $package->manifestVersion,
            'status' => ResourceStatus::Published->value,
            'package_id' => $package->packageId,
        ];

        return new self([...$this->entries, $entry]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        return $this->entries === [] ? null : $this->entries[count($this->entries) - 1];
    }

    public function latestVersion(): ?string
    {
        $latest = $this->latest();

        return $latest === null ? null : (string) ($latest['version'] ?? '');
    }

    public function hasVersion(string $version): bool
    {
        foreach ($this->entries as $entry) {
            if ((string) ($entry['version'] ?? '') === $version) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function versions(): array
    {
        return array_map(static fn (array $e): string => (string) ($e['version'] ?? ''), $this->entries);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->entries;
    }
}
