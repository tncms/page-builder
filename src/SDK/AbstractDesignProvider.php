<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\SDK;

use Plugins\PageBuilder\Contracts\DesignResourceProvider;
use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\Rid;

/**
 * The official base class for Design Resource providers (Phase 26.49B, Rule 1).
 *
 * It implements the frozen {@see DesignResourceProvider} contract in terms of two
 * small responsibilities a channel actually owns:
 *
 *   - {@see key()}       — the stable, unique channel key
 *   - {@see provideResources()} — the resources the channel materialises
 *
 * Everything else (priority defaults, availability, a monotonic revision token,
 * uniform find(), a compliant {@see makeResource()} factory) is provided so every
 * channel presents the identical surface without re-implementing it. A provider
 * MAY override any method — e.g. a network-backed channel should override
 * {@see revision()} with a cheap token and {@see isAvailable()} with a health
 * check — but the defaults already pass {@see ProviderComplianceValidator}.
 *
 * The base class extends the platform; it never modifies it. It reads no global
 * state, holds no persistence, and returns immutable resources only.
 *
 * @see docs/provider-sdk.md
 */
abstract class AbstractDesignProvider implements DesignResourceProvider
{
    /**
     * The stable, unique provider key (e.g. 'community'). Also the default
     * {@see source()} for this channel's RIDs.
     */
    abstract public function key(): string;

    /**
     * The resources this channel currently offers, as immutable DesignResources.
     * Build them with {@see makeResource()} so the source/RID/vendor stay consistent.
     *
     * @return iterable<DesignResource>
     */
    abstract protected function provideResources(): iterable;

    /**
     * The RID source segment for this channel. Defaults to {@see key()}; override
     * only if the channel key and the resource source deliberately differ.
     */
    protected function source(): string
    {
        return $this->key();
    }

    /**
     * Resolution precedence — lower wins. Third-party channels default to 50,
     * ABOVE the builtin fallback (100) but leaving room for higher-precedence
     * first-party channels below.
     */
    public function priority(): int
    {
        return 50;
    }

    /**
     * Whether the channel can serve right now. Static catalogs are always
     * available; a remote channel should override with a health/connectivity check.
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * A monotonic token that changes whenever the resource set changes. The default
     * derives a deterministic signature from the current RIDs + versions — correct
     * for finite catalogs. A large or remote channel SHOULD override this with a
     * cheaper token (e.g. a stored counter bumped on write) to avoid re-materialising.
     */
    public function revision(): int
    {
        $signature = 0;
        foreach ($this->provideResources() as $resource) {
            $signature += crc32($resource->rid.'@'.$resource->version);
        }

        return $signature & 0x7FFFFFFF;
    }

    /**
     * @return iterable<DesignResource>
     */
    public function all(): iterable
    {
        yield from $this->provideResources();
    }

    /**
     * Uniform RID resolution: tolerate malformed RIDs (null, never throw) and scan
     * the channel's resources for an exact match. Override for O(1) lookup when a
     * channel can resolve a RID without materialising the whole set.
     */
    public function find(string $rid): ?DesignResource
    {
        if (! Rid::isValid($rid)) {
            return null;
        }

        foreach ($this->provideResources() as $resource) {
            if ($resource->rid === $rid) {
                return $resource;
            }
        }

        return null;
    }

    /**
     * Build a compliant {@see DesignResource} for this channel. The RID, source and
     * vendor are derived from {@see source()} so identity stays consistent and
     * collision-free; the payload MUST be an id-free, renderable LayoutPayload.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $tags
     * @param  array<string, mixed>|null  $compatibility
     * @param  array<string, mixed>  $distribution  C8 — never affects rendering
     */
    protected function makeResource(
        string $kind,
        string $resourceId,
        string $name,
        string $category,
        array $payload,
        array $tags = [],
        ?string $description = null,
        ?array $compatibility = ['schemaVersion' => '1'],
        array $distribution = ['entitlement' => 'free'],
        string $version = '1.0.0',
    ): DesignResource {
        $source = $this->source();

        return new DesignResource(
            rid: $source.':'.$kind.':'.$resourceId,
            source: $source,
            kind: $kind,
            resourceId: $resourceId,
            version: $version,
            name: $name,
            category: $category,
            tags: $tags,
            payload: $payload,
            vendor: $source,
            description: $description,
            compatibility: $compatibility,
            distribution: $distribution,
        );
    }
}
