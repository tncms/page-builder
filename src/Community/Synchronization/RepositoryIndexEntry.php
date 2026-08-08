<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Synchronization;

use Plugins\PageBuilder\Community\Manifest\ResourceManifest;
use Plugins\PageBuilder\Community\Publishing\ResourceStatus;
use Plugins\PageBuilder\Repository\Rid;

/**
 * One immutable entry of a {@see RepositoryIndex} (Phase 26.50C, Rule 2).
 *
 * An index entry is the transport unit of the synchronization protocol: it carries
 * exactly the metadata needed to detect change — RID, fingerprint, version, provider,
 * a manifest summary, package metadata, a compatibility summary and the updated
 * timestamp — and NOTHING ELSE. There is no payload here; synchronization moves
 * metadata, never resource content (Rules 2, 16). The exposure `status`
 * (`published` / `deprecated` for active entries, `archived` / `removed` for
 * tombstones) is carried so change detection can classify deprecation and archival
 * (Rule 4).
 *
 * @see docs/repository-index.md
 */
final class RepositoryIndexEntry
{
    private const FINGERPRINT = '/^[0-9a-f]{16,}$/i';

    private const VERSION = '/^\d+\.\d+\.\d+/';

    /**
     * @param  array<string, mixed>  $manifestSummary  title / resource_type / category / tags
     * @param  array<string, mixed>|null  $packageMetadata  published package metadata (never a bundle)
     * @param  array<string, mixed>  $compatibilitySummary  page_builder / theme / minimum_builder_version
     */
    public function __construct(
        public readonly string $rid,
        public readonly string $fingerprint,
        public readonly string $version,
        public readonly string $provider,
        public readonly string $status,
        public readonly array $manifestSummary,
        public readonly ?array $packageMetadata,
        public readonly array $compatibilitySummary,
        public readonly int $updatedAt,
        public readonly string $schemaVersion = ResourceManifest::SCHEMA,
    ) {}

    /**
     * Project a manifest (+ its lifecycle status and published package) into an index
     * entry. Only summaries are carried — the payload never enters the index.
     *
     * @param  array<string, mixed>|null  $package
     */
    public static function fromManifest(ResourceManifest $manifest, ResourceStatus $status, ?array $package = null, ?int $updatedAt = null): self
    {
        return new self(
            rid: $manifest->rid,
            fingerprint: $manifest->fingerprint,
            version: $manifest->version,
            provider: $manifest->provider,
            status: $status->value,
            manifestSummary: [
                'title' => $manifest->title,
                'resource_type' => $manifest->resourceType,
                'category' => $manifest->category,
                'tags' => $manifest->tags,
            ],
            packageMetadata: $package,
            compatibilitySummary: [
                'page_builder' => $manifest->compatibility->pageBuilder,
                'theme' => $manifest->compatibility->theme,
                'minimum_builder_version' => $manifest->compatibility->minimumBuilderVersion,
            ],
            updatedAt: $updatedAt ?? (int) ($package['published_at'] ?? 0),
            schemaVersion: $manifest->schema,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            rid: (string) ($data['rid'] ?? ''),
            fingerprint: (string) ($data['fingerprint'] ?? ''),
            version: (string) ($data['version'] ?? ''),
            provider: (string) ($data['provider'] ?? ''),
            status: (string) ($data['status'] ?? ResourceStatus::Published->value),
            manifestSummary: is_array($data['manifest_summary'] ?? null) ? $data['manifest_summary'] : [],
            packageMetadata: is_array($data['package_metadata'] ?? null) ? $data['package_metadata'] : null,
            compatibilitySummary: is_array($data['compatibility_summary'] ?? null) ? $data['compatibility_summary'] : [],
            updatedAt: (int) ($data['updated_at'] ?? 0),
            schemaVersion: (string) ($data['schema_version'] ?? ResourceManifest::SCHEMA),
        );
    }

    /**
     * Whether the CONTENT identity differs (a new fingerprint or version) — the signal
     * for an "updated" classification. Never compares payload (Rule 5).
     */
    public function contentDiffersFrom(self $other): bool
    {
        return $this->fingerprint !== $other->fingerprint || $this->version !== $other->version;
    }

    public function isDeprecated(): bool
    {
        return $this->status === ResourceStatus::Deprecated->value;
    }

    public function isArchivedTombstone(): bool
    {
        return $this->status === ResourceStatus::Archived->value;
    }

    /**
     * Well-formedness gate applied before metadata is accepted into the cache (Rule
     * 13): a valid identity, a hex fingerprint, a semantic version, the shared schema
     * version and a declared compatibility. Compatibility is validated for presence
     * only — it is never resolved or enforced here.
     */
    public function validationError(): ?string
    {
        if (! Rid::isValid($this->rid)) {
            return "'{$this->rid}' is not a valid resource identifier.";
        }
        if (! preg_match(self::FINGERPRINT, $this->fingerprint)) {
            return 'fingerprint must be a non-empty hex content digest.';
        }
        if (! preg_match(self::VERSION, $this->version)) {
            return "version '{$this->version}' must be semantic (e.g. 1.0.0).";
        }
        if ($this->schemaVersion !== ResourceManifest::SCHEMA) {
            return "unsupported manifest schema '{$this->schemaVersion}' (expected '".ResourceManifest::SCHEMA."').";
        }
        if (trim((string) ($this->compatibilitySummary['page_builder'] ?? '')) === ''
            || trim((string) ($this->compatibilitySummary['theme'] ?? '')) === '') {
            return 'compatibility summary must declare page_builder and theme.';
        }

        return null;
    }

    public function isAcceptable(): bool
    {
        return $this->validationError() === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rid' => $this->rid,
            'fingerprint' => $this->fingerprint,
            'version' => $this->version,
            'provider' => $this->provider,
            'status' => $this->status,
            'manifest_summary' => $this->manifestSummary,
            'package_metadata' => $this->packageMetadata,
            'compatibility_summary' => $this->compatibilitySummary,
            'updated_at' => $this->updatedAt,
            'schema_version' => $this->schemaVersion,
        ];
    }
}
