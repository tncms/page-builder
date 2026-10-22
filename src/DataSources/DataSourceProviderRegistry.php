<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

use Plugins\PageBuilder\DataSources\Contracts\DataSourceDefinition;
use Plugins\PageBuilder\DataSources\Contracts\DataSourceProvider;
use Plugins\PageBuilder\DataSources\Contracts\InternalDataSourceProvider;
use Plugins\PageBuilder\DataSources\Exceptions\DataSourceNamespaceViolationException;
use Plugins\PageBuilder\DataSources\Exceptions\DataSourceRegistryFrozenException;
use Plugins\PageBuilder\DataSources\Exceptions\DuplicateDataSourceIdentifierException;
use Plugins\PageBuilder\DataSources\Exceptions\NonCompliantDataSourceProviderException;

/**
 * The single authority through which dynamic data sources enter Page Builder
 * (PB-XREG-2). A sibling of — never merged with — the Component Provider registry
 * and the Design Resource repository.
 *
 * Same lifecycle guarantees as PB-XREG-1:
 *
 *   - server-derived provider identity (declared namespace must equal the
 *     canonical active plugin slug);
 *   - compliance-gated, atomic per provider batch (one bad source rejects the
 *     whole batch without mutating the registry);
 *   - idempotent re-registration only when every contract-relevant property is
 *     identical; any other collision fails closed and keeps the first owner;
 *   - deterministic snapshot/hash independent of registration order;
 *   - one freeze point ({@see \Illuminate\Contracts\Foundation\Application::booted()}),
 *     after which registration throws.
 *
 * Deactivating a provider simply means it never registers on the next request:
 * its ids resolve to {@see DataSourceAvailability::UNAVAILABLE} and stored layouts
 * are never touched.
 */
class DataSourceProviderRegistry
{
    /** The public data-source contract version (semver; additive changes bump minor). */
    public const CONTRACT_VERSION = '1.0.0';

    private bool $frozen = false;

    /** id => definition. @var array<string, DataSourceDefinition> */
    private array $definitions = [];

    /** id => owning provider namespace. @var array<string, string> */
    private array $owners = [];

    /** id => contract description + implementation class (idempotency). @var array<string, array<string, mixed>> */
    private array $fingerprints = [];

    /** namespace => provider version. @var array<string, string> */
    private array $versions = [];

    public function __construct(
        private readonly DataSourceProviderComplianceValidator $validator,
        private readonly DataSourceProviderIdentity $identity,
    ) {}

    /**
     * Certify, identity-check, and atomically register every source a provider
     * contributes.
     *
     * @throws DataSourceRegistryFrozenException
     * @throws NonCompliantDataSourceProviderException
     * @throws DataSourceNamespaceViolationException
     * @throws DuplicateDataSourceIdentifierException
     */
    public function register(DataSourceProvider $provider): DataSourceComplianceReport
    {
        if ($this->frozen) {
            throw DataSourceRegistryFrozenException::forProvider($this->namespaceOf($provider));
        }

        $report = $this->validator->validate($provider);

        if (! $report->passed()) {
            throw new NonCompliantDataSourceProviderException($report);
        }

        $namespace = $this->enforceIdentity($provider);

        $pending = [];
        foreach ($provider->sources() as $definition) {
            $id = trim($definition->id());
            $fingerprint = $this->fingerprint($definition);

            $this->assertNoConflict($id, $namespace, $fingerprint);

            $pending[$id] = ['definition' => $definition, 'fingerprint' => $fingerprint];
        }

        foreach ($pending as $id => $entry) {
            $this->definitions[$id] = $entry['definition'];
            $this->owners[$id] = $namespace;
            $this->fingerprints[$id] = $entry['fingerprint'];
        }

        $this->versions[$namespace] = trim($provider->version());

        return $report;
    }

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

    /** {@see DataSourceAvailability::KNOWN|UNAVAILABLE|UNKNOWN} for a stored identifier. */
    public function availability(string $id): string
    {
        $id = trim($id);

        if (isset($this->definitions[$id])) {
            return DataSourceAvailability::KNOWN;
        }

        return DataSourceIdentifier::isValid($id)
            ? DataSourceAvailability::UNAVAILABLE
            : DataSourceAvailability::UNKNOWN;
    }

    /** The trusted definition for a registered id, or null. */
    public function definition(string $id): ?DataSourceDefinition
    {
        return $this->definitions[trim($id)] ?? null;
    }

    public function ownerOf(string $id): ?string
    {
        return $this->owners[trim($id)] ?? null;
    }

    /**
     * Plain-data editor catalog of registered sources, optionally restricted to
     * those yielding one of `$recordKinds`. Ordered by id.
     *
     * @param  list<string>|null  $recordKinds
     * @return list<array{id: string, label: string, recordKind: string, maxLimit: int, defaultLimit: int, options: list<array<string, mixed>>}>
     */
    public function catalog(?array $recordKinds = null): array
    {
        $out = [];

        foreach ($this->definitions as $id => $definition) {
            // Described live so labels resolve in the CURRENT (Admin UI) locale; the
            // structural contract was already certified at registration.
            try {
                $description = DataSourceProviderComplianceValidator::describe($definition);
            } catch (\Throwable) {
                $description = $this->fingerprints[$id]['contract'];
            }

            if ($recordKinds !== null && ! in_array($description['recordKind'], $recordKinds, true)) {
                continue;
            }

            $out[$id] = $description;
        }

        ksort($out);

        return array_values($out);
    }

    /**
     * Deterministic snapshot: id => {namespace, version, recordKind, maxLimit, defaultLimit, options}.
     *
     * @return array<string, array<string, mixed>>
     */
    public function snapshot(): array
    {
        $snapshot = [];

        foreach ($this->owners as $id => $namespace) {
            $contract = $this->fingerprints[$id]['contract'];
            $snapshot[$id] = [
                'namespace' => $namespace,
                'version' => $this->versions[$namespace] ?? '',
                'recordKind' => $contract['recordKind'],
                'maxLimit' => $contract['maxLimit'],
                'defaultLimit' => $contract['defaultLimit'],
                'options' => $contract['options'],
            ];
        }

        ksort($snapshot);

        return $snapshot;
    }

    public function snapshotHash(): string
    {
        return hash('sha256', (string) json_encode($this->snapshot()));
    }

    // ---- internals ------------------------------------------------------

    private function enforceIdentity(DataSourceProvider $provider): string
    {
        $declared = $this->namespaceOf($provider);

        if ($provider instanceof InternalDataSourceProvider) {
            return $declared; // validator guaranteed the reserved namespace
        }

        $canonical = $this->identity->resolve($provider);

        if ($canonical === null) {
            throw DataSourceNamespaceViolationException::forReason(
                'cannot derive the canonical installed-plugin slug for provider "'.$provider::class.'".'
            );
        }

        if ($canonical !== $declared) {
            throw DataSourceNamespaceViolationException::forReason(
                'declared namespace "'.$declared.'" does not match canonical plugin slug "'.$canonical.'".'
            );
        }

        return $canonical;
    }

    /** @return array{contract: array<string, mixed>, class: string} */
    private function fingerprint(DataSourceDefinition $definition): array
    {
        return [
            'contract' => DataSourceProviderComplianceValidator::describe($definition),
            'class' => $definition::class,
        ];
    }

    /** @param  array<string, mixed>  $fingerprint */
    private function assertNoConflict(string $id, string $namespace, array $fingerprint): void
    {
        $owner = $this->owners[$id] ?? null;

        if ($owner === null) {
            return;
        }

        if ($owner === $namespace && ($this->fingerprints[$id] ?? null) === $fingerprint) {
            return;
        }

        throw DuplicateDataSourceIdentifierException::forIdentifier($id, $owner);
    }

    private function namespaceOf(DataSourceProvider $provider): string
    {
        try {
            return trim($provider->namespace());
        } catch (\Throwable) {
            return '';
        }
    }
}
