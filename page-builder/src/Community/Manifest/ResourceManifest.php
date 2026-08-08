<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Manifest;

use Plugins\PageBuilder\Community\Manifest\Contracts\ManifestContract;

/**
 * The official, immutable **Resource Manifest** (Phase 26.50A, Rules 2 & 3).
 *
 * A manifest is the platform-neutral interchange envelope for one distributable
 * Design Resource: identity (rid / fingerprint), classification, compatibility,
 * dependencies, license, assets, author and preview — and NOTHING ELSE (no Builder
 * runtime, no proprietary or hidden fields). It is the ONE format Community,
 * Marketplace, PRO, Enterprise and Private repositories all share (Rules 12, 13).
 *
 * It is a readonly value object: immutable after publishing. A content change is a
 * NEW version — a new manifest with a new fingerprint — never an in-place edit
 * (Rule 3). The payload/preview binaries live beside the manifest in the repository
 * store; the manifest itself carries only metadata (Rule 4).
 *
 * @see docs/resource-manifest.md
 */
final class ResourceManifest implements ManifestContract
{
    public const SCHEMA = '1.0';

    /**
     * @param  array<int, string>  $tags
     * @param  array<int, ResourceDependency>  $dependencies
     * @param  array<int, AssetReference>  $assets
     */
    public function __construct(
        public readonly string $schema,
        public readonly string $rid,
        public readonly string $fingerprint,
        public readonly string $resourceType,
        public readonly string $provider,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $category,
        public readonly array $tags,
        public readonly string $version,
        public readonly AuthorMetadata $author,
        public readonly CompatibilityMetadata $compatibility,
        public readonly LicenseMetadata $license,
        public readonly array $dependencies,
        public readonly array $assets,
        public readonly PreviewMetadata $preview,
    ) {}

    public function schema(): string
    {
        return $this->schema;
    }

    public function rid(): string
    {
        return $this->rid;
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    public function resourceType(): string
    {
        return $this->resourceType;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function version(): string
    {
        return $this->version;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema' => $this->schema,
            'rid' => $this->rid,
            'fingerprint' => $this->fingerprint,
            'resource_type' => $this->resourceType,
            'provider' => $this->provider,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'tags' => $this->tags,
            'version' => $this->version,
            'author' => $this->author->toArray(),
            'compatibility' => $this->compatibility->toArray(),
            'license' => $this->license->toArray(),
            'dependencies' => array_map(static fn (ResourceDependency $d): array => $d->toArray(), $this->dependencies),
            'assets' => array_map(static fn (AssetReference $a): array => $a->toArray(), $this->assets),
            'preview' => $this->preview->toArray(),
        ];
    }
}
