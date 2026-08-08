<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Package;

use Plugins\PageBuilder\Package\Exceptions\PackageExportException;
use Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface;
use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\Rid;

/**
 * Exports a Design Resource into a portable `.tncms-design` package (FREE v1).
 *
 * Resources are resolved through the repository (the single source of truth). The
 * exporter validates the resource against the portable-package boundary, strips
 * runtime-only node IDs, and produces a canonical, deterministic package document
 * (same resource → byte-identical bytes). It ships NO bytes: assets stay URL
 * references (there is no asset downloader).
 */
final class DesignPackageExporter
{
    /** Kinds that can be exported in FREE v1 (kits are future). */
    private const EXPORTABLE = [Rid::KIND_BLOCK, Rid::KIND_SECTION, Rid::KIND_PAGE];

    public function __construct(
        private readonly DesignResourceRepositoryInterface $repository,
    ) {
    }

    /**
     * @throws PackageExportException
     */
    public function exportRid(string $rid): DesignPackageManifest
    {
        $resource = $this->repository->tryFind($rid);
        if ($resource === null) {
            throw PackageExportException::resourceNotFound($rid);
        }

        return $this->export($resource);
    }

    /**
     * @throws PackageExportException
     */
    public function export(DesignResource $resource): DesignPackageManifest
    {
        if (! in_array($resource->kind, self::EXPORTABLE, true)) {
            throw PackageExportException::unsupportedKind($resource->kind);
        }

        // Boundary checks BEFORE producing bytes.
        $forbidden = DesignPackagePayload::forbiddenReferences($resource->payload);
        if ($forbidden !== []) {
            throw PackageExportException::forbiddenReference($forbidden);
        }
        $unsafe = DesignPackagePayload::unsafeAssets($resource->payload);
        if ($unsafe !== []) {
            throw PackageExportException::unsafeAsset($unsafe);
        }

        $entry = $this->resourceEntry($resource);
        $resources = [$entry];

        $metadata = [
            'id' => $resource->resourceId,
            'name' => $resource->name,
            // Provenance only — the importer always re-sources to `imported`.
            'source' => $resource->source,
            // Deterministic export: no wall-clock value baked into the bytes.
            'createdAt' => null,
        ];

        return new DesignPackageManifest(
            schemaVersion: DesignPackageManifest::SCHEMA_VERSION,
            type: DesignPackageManifest::TYPE,
            metadata: $metadata,
            resources: $resources,
            checksum: DesignPackageManifest::checksumFor($resources),
        );
    }

    public function toJson(DesignPackageManifest $manifest): string
    {
        return (string) json_encode(
            $manifest->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    public function filenameFor(DesignResource $resource): string
    {
        return $resource->resourceId.'.'.DesignPackageManifest::EXTENSION;
    }

    /**
     * Build the id-free, portable resource body. Identity (kind/resourceId/version),
     * classification, tokens and safe asset URLs are preserved.
     *
     * @return array<string, mixed>
     */
    private function resourceEntry(DesignResource $resource): array
    {
        return [
            'kind' => $resource->kind,
            'resourceId' => $resource->resourceId,
            'version' => $resource->version,
            'name' => $resource->name,
            'category' => $resource->category,
            'tags' => $resource->tags,
            'description' => $resource->description,
            'preview' => $resource->preview,
            'compatibility' => $resource->compatibility,
            'distribution' => $resource->distribution,
            'members' => $resource->members,
            'payload' => DesignPackagePayload::stripIds($resource->payload),
        ];
    }
}
