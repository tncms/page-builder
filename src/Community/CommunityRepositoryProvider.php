<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community;

use Plugins\PageBuilder\Community\Manifest\ManifestParser;
use Plugins\PageBuilder\Community\Manifest\ManifestValidator;
use Plugins\PageBuilder\Community\Manifest\ResourceManifest;
use Plugins\PageBuilder\Community\Publishing\ResourceStatus;
use Plugins\PageBuilder\Community\Synchronization\RepositoryIndex;
use Plugins\PageBuilder\Community\Synchronization\SynchronizationCache;
use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\Rid;
use Plugins\PageBuilder\SDK\AbstractDesignProvider;

/**
 * The official **Community Repository Provider** (Phase 26.50A, Rules 1 & 11).
 *
 * Community is NOT a bespoke repository — it is another Provider built entirely on
 * the frozen Provider SDK ({@see AbstractDesignProvider}). It contributes resources
 * to the ONE {@see \Plugins\PageBuilder\Repository\DesignResourceRepository}; it owns
 * no Repository, no Registry, no Runtime, no Builder (Rules 4, 5). Every resource is
 * described by the platform-neutral {@see ResourceManifest} — the interchange format
 * that Marketplace, PRO, Enterprise and Private providers reuse verbatim (Rule 12).
 *
 * The provider exposes the uniform SDK surface — list ({@see all()}),
 * {@see find()} — plus the canonical {@see manifest()} accessor that every
 * manifest-backed provider shares. Search, preview and metadata are served by the
 * platform (repository query, Runtime Preview keyed by RID, and
 * {@see DesignResource::toMetadata()}); this provider only contributes the data
 * (Rule 11). It reads local storage only — no network, download, sync or publishing
 * (Rule 18).
 *
 * @see docs/community-repository.md
 */
final class CommunityRepositoryProvider extends AbstractDesignProvider
{
    public const KEY = 'community';

    public const SOURCE = 'community';

    /** @var array<string, DesignResource>|null in-request cache, rid => resource */
    private ?array $resources = null;

    /** @var array<string, ResourceManifest>|null in-request cache, rid => manifest */
    private ?array $manifests = null;

    private ?string $signature = null;

    public function __construct(
        private readonly CommunityResourceStore $store,
        private readonly ManifestParser $parser = new ManifestParser,
        private readonly ManifestValidator $validator = new ManifestValidator,
        private readonly ?SynchronizationCache $syncCache = null,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function priority(): int
    {
        // A distinct external channel. Its dedicated `community` source keeps RIDs
        // disjoint from every other provider, so this ordering is effectively moot.
        return 45;
    }

    public function revision(): int
    {
        $signature = $this->store->signature();

        return $signature === '' ? 0 : (int) hexdec($signature);
    }

    /**
     * @return iterable<DesignResource>
     */
    protected function provideResources(): iterable
    {
        return array_values($this->load());
    }

    /**
     * The canonical manifest accessor — the one method every manifest-backed
     * provider shares (Community, Marketplace, PRO, Enterprise).
     */
    public function manifest(string $rid): ?ResourceManifest
    {
        $this->load();

        return $this->manifests[$rid] ?? null;
    }

    /**
     * Every valid manifest this provider serves.
     *
     * @return array<int, ResourceManifest>
     */
    public function manifests(): array
    {
        $this->load();

        return array_values($this->manifests ?? []);
    }

    /**
     * The synchronized Repository Index for this provider's channel (Phase 26.50C,
     * Rule 11). When a {@see SynchronizationCache} is wired, the provider can read the
     * metadata last synchronized from remote repositories. This is METADATA
     * AWARENESS only: the provider still serves for insertion exclusively the local,
     * payload-bearing resources from its store (synchronization transports no payload
     * and installs nothing — Rule 16), so the ONE repository, registry and library are
     * unchanged.
     */
    public function synchronizedIndex(): ?RepositoryIndex
    {
        return $this->syncCache?->read(self::SOURCE);
    }

    /**
     * @return array<string, DesignResource>
     */
    private function load(): array
    {
        $signature = $this->store->signature();
        if ($this->resources !== null && $this->signature === $signature) {
            return $this->resources;
        }

        $resources = [];
        $manifests = [];

        foreach ($this->store->all() as $record) {
            $manifest = $this->parser->fromArray($record['manifest']);

            // Reject malformed manifests and foreign channels (Rules 15, and the
            // fixed `community` source keeps RIDs disjoint).
            if ($manifest->provider !== self::SOURCE || ! $this->validator->validate($manifest)->passed()) {
                continue;
            }

            // Phase 26.50B — expose ONLY published resources (deprecated ones stay
            // visible with a warning; drafts/validated/packaged/archived stay local).
            // An absent lifecycle means an externally-provided (offline / mirror)
            // resource, which is published by convention (Rules 9, 13).
            $status = ResourceStatus::tryFromString($record['lifecycle']['status'] ?? null) ?? ResourceStatus::Published;
            if (! $status->isExposed()) {
                continue;
            }

            $resource = $this->materialize($manifest, $record['payload'], $status->isDeprecated());
            if ($resource === null) {
                continue;
            }

            $resources[$resource->rid] = $resource;
            $manifests[$resource->rid] = $manifest;
        }

        $this->resources = $resources;
        $this->manifests = $manifests;
        $this->signature = $signature;

        return $resources;
    }

    /**
     * Project a validated manifest + payload into an immutable DesignResource. The
     * source is FIXED to `community`; a manifest's declared identity is trusted only
     * after validation confirmed its rid source equals the provider.
     *
     * @param  array<string, mixed>  $payload
     */
    private function materialize(ResourceManifest $manifest, array $payload, bool $deprecated = false): ?DesignResource
    {
        if (! Rid::isValid($manifest->rid)) {
            return null;
        }

        $parsed = Rid::parse($manifest->rid);
        if ($parsed->source !== self::SOURCE) {
            return null;
        }

        $preview = $manifest->preview->isEmpty() ? null : $manifest->preview->toArray();

        return new DesignResource(
            rid: $manifest->rid,
            source: self::SOURCE,
            kind: $parsed->kind,
            resourceId: $parsed->resourceId,
            version: $manifest->version,
            name: $manifest->title,
            category: $manifest->category,
            tags: $manifest->tags,
            payload: $payload,
            vendor: $manifest->author->name !== '' ? $manifest->author->name : self::SOURCE,
            description: $manifest->description,
            preview: $preview,
            compatibility: array_merge(['schemaVersion' => '1'], $manifest->compatibility->toArray()),
            distribution: $this->distribution($manifest, $deprecated),
            package: ['source' => self::SOURCE, 'author' => $manifest->author->name],
        );
    }

    /**
     * Map the manifest license (C8) to the resource's distribution metadata. Community
     * resources are free/open; reserved commercial types are recorded as metadata
     * only — no commerce, no capability gating is introduced here (Rule 18). A
     * deprecated resource is flagged so the Library can warn (Rule 9).
     *
     * @return array<string, mixed>
     */
    private function distribution(ResourceManifest $manifest, bool $deprecated = false): array
    {
        return [
            'entitlement' => $manifest->license->isCommunity() ? 'free' : $manifest->license->type,
            'license' => $manifest->license->toArray(),
            'lifecycle_status' => $deprecated ? ResourceStatus::Deprecated->value : ResourceStatus::Published->value,
            'deprecated' => $deprecated,
        ];
    }
}
