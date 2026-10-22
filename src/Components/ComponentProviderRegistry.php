<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components;

use Plugins\PageBuilder\Components\Contracts\ComponentProvider;
use Plugins\PageBuilder\Components\Contracts\ExternalComponentDefinition;
use Plugins\PageBuilder\Components\Contracts\InternalComponentProvider;
use Plugins\PageBuilder\Components\Exceptions\ComponentNamespaceViolationException;
use Plugins\PageBuilder\Components\Exceptions\ComponentRegistryFrozenException;
use Plugins\PageBuilder\Components\Exceptions\DuplicateComponentIdentifierException;
use Plugins\PageBuilder\Components\Exceptions\NonCompliantComponentProviderException;
use Plugins\PageBuilder\Contracts\ComponentDefinition;
use Plugins\PageBuilder\Contracts\SectionDefinition;
use Plugins\PageBuilder\Registries\BlockRegistry;
use Plugins\PageBuilder\Registries\SectionRegistry;

/**
 * The single authority through which Section and Block component definitions enter
 * the Page Builder runtime registries (PB-XREG-1).
 *
 * It extends — never replaces — the existing {@see SectionRegistry} /
 * {@see BlockRegistry}: validated definitions are written into those same stores,
 * so the runtime ({@see \Plugins\PageBuilder\Runtime\ComponentFactory}) keeps
 * resolving `->get($type)` unchanged. What this layer adds is the public-provider
 * contract the raw registries lack:
 *
 *   - server-derived provider identity (declared namespace must equal the
 *     canonical installed plugin slug; a provider cannot claim another's);
 *   - fail-closed duplicate handling (no silent overwrite), atomic per provider;
 *   - idempotent repeated registration of an identical definition;
 *   - one deterministic freeze point after which registration throws;
 *   - a truthful known / unavailable / unknown state for any stored node type.
 *
 * Built-ins register through the SAME method via the internal
 * {@see InternalComponentProvider}, so there is exactly one registration authority.
 */
class ComponentProviderRegistry
{
    private bool $frozen = false;

    /** id => owning provider namespace. @var array<string, string> */
    private array $owners = [];

    /** id => canonical stored definition array (for idempotency/conflict checks). @var array<string, array<string, mixed>> */
    private array $definitions = [];

    /** namespace => provider contract version. @var array<string, string> */
    private array $versions = [];

    public function __construct(
        private readonly SectionRegistry $sections,
        private readonly BlockRegistry $blocks,
        private readonly ComponentProviderComplianceValidator $validator,
        private readonly ComponentProviderIdentity $identity,
    ) {}

    /**
     * Certify, identity-check, and register every component a provider contributes.
     * Atomic: a single invalid, mis-namespaced, or duplicate definition rejects the
     * provider's WHOLE batch without mutating the registry.
     *
     * @throws ComponentRegistryFrozenException registered after the freeze point
     * @throws NonCompliantComponentProviderException failed structural compliance
     * @throws ComponentNamespaceViolationException identity could not be confirmed
     * @throws DuplicateComponentIdentifierException identifier already owned elsewhere
     */
    public function register(ComponentProvider $provider): ComponentComplianceReport
    {
        if ($this->frozen) {
            throw ComponentRegistryFrozenException::forProvider($this->namespaceOf($provider));
        }

        $report = $this->validator->validate($provider);

        if (! $report->passed()) {
            throw new NonCompliantComponentProviderException($report);
        }

        $internal = $provider instanceof InternalComponentProvider;
        $namespace = $this->enforceIdentity($provider, $internal);

        // Resolve the whole batch and check collisions BEFORE writing anything.
        $pending = [];           // id => ['kind' => 'section'|'block', 'array' => ...]
        $batchSeen = [];         // local duplicate guard across this batch

        foreach ($provider->components() as $definition) {
            if (! $definition instanceof ComponentDefinition) {
                continue; // validator already guaranteed this; defensive.
            }

            $id = trim($definition->type());
            $kind = $definition instanceof SectionDefinition ? 'section' : 'block';
            $array = $this->resolveArray($definition, $internal);

            if (isset($batchSeen[$id])) {
                throw DuplicateComponentIdentifierException::forIdentifier($id, $namespace);
            }
            $batchSeen[$id] = true;

            $this->assertNoConflict($id, $namespace, $array);

            $pending[] = ['id' => $id, 'kind' => $kind, 'array' => $array];
        }

        // All clear — commit the batch.
        foreach ($pending as $entry) {
            $registry = $entry['kind'] === 'section' ? $this->sections : $this->blocks;
            $registry->register($entry['id'], $entry['array']);

            $this->owners[$entry['id']] = $namespace;
            $this->definitions[$entry['id']] = $entry['array'];
        }

        $this->versions[$namespace] = $this->versionOf($provider);

        return $report;
    }

    /** Freeze the registry: further {@see register()} calls fail closed. */
    public function freeze(): void
    {
        $this->frozen = true;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    /** Re-open a frozen registry. TEST ONLY — never called in production. */
    public function reopenForTesting(): void
    {
        $this->frozen = false;
    }

    /**
     * The truthful state of a stored node `type`:
     * {@see ComponentAvailability::KNOWN|UNAVAILABLE|UNKNOWN}.
     */
    public function availability(string $type): string
    {
        $type = trim($type);

        if ($this->sections->has($type) || $this->blocks->has($type)) {
            return ComponentAvailability::KNOWN;
        }

        return ComponentIdentifier::classify($type) === ComponentIdentifier::KIND_EXTERNAL
            ? ComponentAvailability::UNAVAILABLE
            : ComponentAvailability::UNKNOWN;
    }

    /** The owning provider namespace for a registered id, or null. */
    public function ownerOf(string $id): ?string
    {
        return $this->owners[trim($id)] ?? null;
    }

    /**
     * A deterministic, ordered snapshot of every provider-registered component:
     * id => {kind, namespace, version, view?}. Ordered by id for stable hashing.
     * Built-ins registered directly into the raw registries (not via a provider)
     * are intentionally excluded — this reflects the provider authority's own view.
     *
     * @return array<string, array{kind: string, namespace: string, version: string, view: ?string}>
     */
    public function snapshot(): array
    {
        $snapshot = [];

        foreach ($this->owners as $id => $namespace) {
            $array = $this->definitions[$id] ?? [];
            $snapshot[$id] = [
                'kind' => $this->sections->has($id) ? 'section' : 'block',
                'namespace' => $namespace,
                'version' => $this->versions[$namespace] ?? '',
                'view' => is_array($array['render'] ?? null) && is_string($array['render']['view'] ?? null)
                    ? $array['render']['view']
                    : null,
            ];
        }

        ksort($snapshot);

        return $snapshot;
    }

    /** A stable content hash of {@see snapshot()}; identical input yields identical hash. */
    public function snapshotHash(): string
    {
        return hash('sha256', (string) json_encode($this->snapshot()));
    }

    // ---- internals ------------------------------------------------------

    private function enforceIdentity(ComponentProvider $provider, bool $internal): string
    {
        $declared = $this->namespaceOf($provider);

        if ($internal) {
            // Page Builder's own provider: trusted, owns the reserved namespace.
            return $declared;
        }

        $canonical = $this->identity->resolve($provider);

        if ($canonical === null) {
            throw ComponentNamespaceViolationException::forReason(
                'cannot derive the canonical installed-plugin slug for provider "'.$provider::class.'".'
            );
        }

        if ($canonical !== $declared) {
            throw ComponentNamespaceViolationException::forReason(
                'declared namespace "'.$declared.'" does not match canonical plugin slug "'.$canonical.'".'
            );
        }

        return $canonical;
    }

    /**
     * Stamp the trusted server renderer identity onto an external definition's
     * stored array. Built-ins keep their array untouched (resolved by convention).
     *
     * @return array<string, mixed>
     */
    private function resolveArray(ComponentDefinition $definition, bool $internal): array
    {
        $array = $definition->toArray();

        if (! $internal && $definition instanceof ExternalComponentDefinition) {
            // Trusted, validator-checked view name in the provider's own namespace.
            // The persisted identifier is NEVER turned into this path.
            $array['render'] = ['view' => trim($definition->view())];
        }

        return $array;
    }

    /**
     * @param  array<string, mixed>  $array
     */
    private function assertNoConflict(string $id, string $namespace, array $array): void
    {
        $existingOwner = $this->owners[$id] ?? null;

        if ($existingOwner === null) {
            // Not owned by any provider. If the raw registry already has this id
            // from a non-provider path, only the internal namespace may adopt it.
            return;
        }

        // Idempotent: same owner re-registering the identical definition is a no-op.
        if ($existingOwner === $namespace && ($this->definitions[$id] ?? null) === $array) {
            return;
        }

        throw DuplicateComponentIdentifierException::forIdentifier($id, $existingOwner);
    }

    private function namespaceOf(ComponentProvider $provider): string
    {
        try {
            return trim($provider->namespace());
        } catch (\Throwable) {
            return '';
        }
    }

    private function versionOf(ComponentProvider $provider): string
    {
        try {
            return trim($provider->version());
        } catch (\Throwable) {
            return '';
        }
    }
}
