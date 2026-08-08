<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\SDK;

use Plugins\PageBuilder\Contracts\DesignResourceProvider;
use Plugins\PageBuilder\Contracts\RegistryContract;
use Plugins\PageBuilder\Contracts\RepositoryContract;
use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\DesignResourceRepository;
use Plugins\PageBuilder\Repository\ResourceQuery;
use Plugins\PageBuilder\Repository\Rid;
use Throwable;

/**
 * The ONE compliance validator for Design Resource providers (Phase 26.49B, Rule 3).
 *
 * It verifies that a provider honours the frozen provider contract exactly, so
 * that any current or future channel (Community, Marketplace, PRO, Portable
 * Package) can be certified against the SAME rules the shipped providers pass.
 * It never mutates the platform: every check runs the provider against a
 * throwaway {@see DesignResourceRepository} instance, never the live one.
 *
 * The checks map one-to-one to the contract obligations and the Rule 6 provider
 * capabilities (list, find, search, availability, preview, metadata):
 *
 *   key          — stable, unique, lowercase slug
 *   priority     — a stable non-negative integer precedence
 *   revision     — a stable non-negative monotonic token
 *   availability — isAvailable() is a stable boolean
 *   all          — list: iterable of valid, unique-RID DesignResource objects
 *   find         — find(rid) round-trips all(); unknown/malformed RIDs return null
 *   search       — the repository can query & search the provider's resources
 *   preview      — every listed resource is RID-resolvable (Runtime Preview keys on RID)
 *   metadata     — every resource exposes the required metadata projection
 *   isolation    — the provider is ONLY a provider (never also Repository/Registry)
 *
 * @see docs/provider-compliance.md
 */
final class ProviderComplianceValidator
{
    /** Reserved provider keys owned by the shipped platform providers. */
    private const RESERVED_KEYS = ['local', 'starter', 'package', 'imported', 'my-designs'];

    private const SLUG = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /**
     * Certify a provider. The returned report passes only if EVERY check passes.
     *
     * @param  array<int, string>  $reservedKeys  extra keys to treat as taken (e.g. the live repo's keys)
     */
    public function validate(DesignResourceProvider $provider, array $reservedKeys = []): ComplianceReport
    {
        $key = $this->safeKey($provider);

        $checks = [
            $this->checkKey($provider, $reservedKeys),
            $this->checkPriority($provider),
            $this->checkRevision($provider),
            $this->checkAvailability($provider),
            $this->checkAll($provider),
            $this->checkFind($provider),
            $this->checkSearch($provider),
            $this->checkPreview($provider),
            $this->checkMetadata($provider),
            $this->checkIsolation($provider),
        ];

        return new ComplianceReport($key, $checks);
    }

    public function isCompliant(DesignResourceProvider $provider): bool
    {
        return $this->validate($provider)->passed();
    }

    // ---- individual checks ---------------------------------------------------

    private function checkKey(DesignResourceProvider $provider, array $reservedKeys): ComplianceCheck
    {
        try {
            $key = $provider->key();
        } catch (Throwable $e) {
            return ComplianceCheck::fail('key', 'key() threw: '.$e->getMessage());
        }

        if ($key === '' || ! preg_match(self::SLUG, $key)) {
            return ComplianceCheck::fail('key', "key '{$key}' must be a non-empty lowercase slug.");
        }
        if ($key !== $provider->key()) {
            return ComplianceCheck::fail('key', 'key() must be stable across calls.');
        }

        $taken = array_merge(self::RESERVED_KEYS, $reservedKeys);
        if (in_array($key, $taken, true)) {
            return ComplianceCheck::fail('key', "key '{$key}' is already taken by another provider.");
        }

        return ComplianceCheck::pass('key');
    }

    private function checkPriority(DesignResourceProvider $provider): ComplianceCheck
    {
        try {
            $priority = $provider->priority();
        } catch (Throwable $e) {
            return ComplianceCheck::fail('priority', 'priority() threw: '.$e->getMessage());
        }

        if ($priority < 0) {
            return ComplianceCheck::fail('priority', 'priority() must be a non-negative integer.');
        }
        if ($priority !== $provider->priority()) {
            return ComplianceCheck::fail('priority', 'priority() must be stable across calls.');
        }

        return ComplianceCheck::pass('priority');
    }

    private function checkRevision(DesignResourceProvider $provider): ComplianceCheck
    {
        try {
            $revision = $provider->revision();
        } catch (Throwable $e) {
            return ComplianceCheck::fail('revision', 'revision() threw: '.$e->getMessage());
        }

        if ($revision < 0) {
            return ComplianceCheck::fail('revision', 'revision() must be a non-negative token.');
        }
        if ($revision !== $provider->revision()) {
            return ComplianceCheck::fail('revision', 'revision() must be stable while the resource set is unchanged.');
        }

        return ComplianceCheck::pass('revision');
    }

    private function checkAvailability(DesignResourceProvider $provider): ComplianceCheck
    {
        try {
            $available = $provider->isAvailable();
        } catch (Throwable $e) {
            return ComplianceCheck::fail('availability', 'isAvailable() threw: '.$e->getMessage());
        }

        if ($available !== $provider->isAvailable()) {
            return ComplianceCheck::fail('availability', 'isAvailable() must be stable across calls.');
        }

        return ComplianceCheck::pass('availability');
    }

    private function checkAll(DesignResourceProvider $provider): ComplianceCheck
    {
        try {
            $resources = $this->materialise($provider);
        } catch (Throwable $e) {
            return ComplianceCheck::fail('all', 'all() threw: '.$e->getMessage());
        }

        $seen = [];
        foreach ($resources as $resource) {
            if (! $resource instanceof DesignResource) {
                return ComplianceCheck::fail('all', 'all() must yield DesignResource objects only.');
            }
            if (! Rid::isValid($resource->rid)) {
                return ComplianceCheck::fail('all', "resource RID '{$resource->rid}' is not a valid identifier.");
            }
            if (isset($seen[$resource->rid])) {
                return ComplianceCheck::fail('all', "duplicate RID '{$resource->rid}' within the provider.");
            }
            $seen[$resource->rid] = true;
        }

        return ComplianceCheck::pass('all');
    }

    private function checkFind(DesignResourceProvider $provider): ComplianceCheck
    {
        try {
            $resources = $this->materialise($provider);

            foreach ($resources as $resource) {
                $found = $provider->find($resource->rid);
                if ($found === null || $found->rid !== $resource->rid) {
                    return ComplianceCheck::fail('find', "find('{$resource->rid}') must round-trip all().");
                }
            }

            // A RID this provider does not own must resolve to null, not an error.
            if ($provider->find('nonexistent:page:does-not-exist-'.substr(md5($provider->key()), 0, 8)) !== null) {
                return ComplianceCheck::fail('find', 'find() must return null for an unknown RID.');
            }

            // A malformed RID must be tolerated (null), never throw.
            if ($provider->find('not-a-valid-rid') !== null) {
                return ComplianceCheck::fail('find', 'find() must return null for a malformed RID.');
            }
        } catch (Throwable $e) {
            return ComplianceCheck::fail('find', 'find() threw: '.$e->getMessage());
        }

        return ComplianceCheck::pass('find');
    }

    private function checkSearch(DesignResourceProvider $provider): ComplianceCheck
    {
        try {
            $repository = new DesignResourceRepository;
            $repository->registerProvider($provider);

            $expected = [];
            foreach ($this->materialise($provider) as $resource) {
                $expected[$resource->rid] = $resource->name;
            }

            // An empty query surfaces every resource the provider offers.
            $all = $repository->query(ResourceQuery::make());
            foreach (array_keys($expected) as $rid) {
                if (! in_array($rid, $all->rids(), true)) {
                    return ComplianceCheck::fail('search', "resource '{$rid}' is not queryable through the repository.");
                }
            }

            // A search term drawn from a resource name matches that resource.
            if ($expected !== []) {
                $rid = array_key_first($expected);
                $term = $this->firstWord($expected[$rid]);
                if ($term !== '') {
                    $hits = $repository->query(ResourceQuery::make()->search($term))->rids();
                    if (! in_array($rid, $hits, true)) {
                        return ComplianceCheck::fail('search', "search('{$term}') must match resource '{$rid}'.");
                    }
                }
            }
        } catch (Throwable $e) {
            return ComplianceCheck::fail('search', 'search failed: '.$e->getMessage());
        }

        return ComplianceCheck::pass('search');
    }

    private function checkPreview(DesignResourceProvider $provider): ComplianceCheck
    {
        // Preview is served exclusively by the one Runtime Preview route, keyed by
        // RID. The provider satisfies "preview" iff every listed resource is
        // RID-resolvable (so the preview engine can resolve and render it) and
        // exposes a renderable, id-free payload.
        try {
            foreach ($this->materialise($provider) as $resource) {
                if ($provider->find($resource->rid) === null) {
                    return ComplianceCheck::fail('preview', "resource '{$resource->rid}' is not RID-resolvable for preview.");
                }
                if (! $resource->isKit() && $resource->payload === []) {
                    return ComplianceCheck::fail('preview', "resource '{$resource->rid}' has an empty payload — nothing to preview.");
                }
            }
        } catch (Throwable $e) {
            return ComplianceCheck::fail('preview', 'preview check failed: '.$e->getMessage());
        }

        return ComplianceCheck::pass('preview');
    }

    private function checkMetadata(DesignResourceProvider $provider): ComplianceCheck
    {
        $required = ['rid', 'source', 'kind', 'resourceId', 'name'];

        try {
            foreach ($this->materialise($provider) as $resource) {
                $metadata = $resource->toMetadata();
                foreach ($required as $field) {
                    if (! array_key_exists($field, $metadata)) {
                        return ComplianceCheck::fail('metadata', "resource '{$resource->rid}' metadata is missing '{$field}'.");
                    }
                }
            }
        } catch (Throwable $e) {
            return ComplianceCheck::fail('metadata', 'metadata check failed: '.$e->getMessage());
        }

        return ComplianceCheck::pass('metadata');
    }

    private function checkIsolation(DesignResourceProvider $provider): ComplianceCheck
    {
        // Rule 5 / Rule 10: a provider EXPOSES resources only. It must never be — or
        // masquerade as — the Repository or the Registry it plugs into.
        if ($provider instanceof RepositoryContract) {
            return ComplianceCheck::fail('isolation', 'a provider must not also be the Repository (Rule 10).');
        }
        if ($provider instanceof RegistryContract) {
            return ComplianceCheck::fail('isolation', 'a provider must not also be the Registry (Rule 10).');
        }

        return ComplianceCheck::pass('isolation');
    }

    // ---- helpers -------------------------------------------------------------

    /**
     * @return array<int, DesignResource>
     */
    private function materialise(DesignResourceProvider $provider): array
    {
        $out = [];
        foreach ($provider->all() as $resource) {
            $out[] = $resource;
        }

        return $out;
    }

    private function safeKey(DesignResourceProvider $provider): string
    {
        try {
            return $provider->key();
        } catch (Throwable) {
            return $provider::class;
        }
    }

    private function firstWord(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $trimmed) ?: [];

        return (string) ($parts[0] ?? '');
    }
}
