<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

use Plugins\PageBuilder\DataSources\Contracts\DataSourceDefinition;
use Plugins\PageBuilder\DataSources\Contracts\DataSourceProvider;
use Plugins\PageBuilder\DataSources\Contracts\InternalDataSourceProvider;
use Throwable;

/**
 * Certifies that a {@see DataSourceProvider} satisfies the PB-XREG-2 public
 * contract before it may register.
 *
 * Checks (each a named obligation): namespace grammar/reservation, provider
 * version, well-formed bounded source set, canonical identifiers in the provider's
 * own namespace, no duplicates in the batch, record kinds, limits within the
 * global ceiling, labels, bounded declarative options, determinism, and
 * provider/registry isolation.
 *
 * Structural only: it never calls fetch(), the database, the network, or Core.
 * The server-derived identity check belongs to {@see DataSourceProviderRegistry}.
 */
final class DataSourceProviderComplianceValidator
{
    public function validate(DataSourceProvider $provider): DataSourceComplianceReport
    {
        $internal = $provider instanceof InternalDataSourceProvider;

        return new DataSourceComplianceReport($this->safeNamespace($provider), [
            $this->checkNamespace($provider, $internal),
            $this->checkVersion($provider),
            $this->checkSources($provider),
            $this->checkIdentifiers($provider, $internal),
            $this->checkDuplicates($provider),
            $this->checkRecordKinds($provider),
            $this->checkLimits($provider),
            $this->checkLabels($provider),
            $this->checkOptions($provider),
            $this->checkDeterminism($provider),
            $this->checkIsolation($provider),
        ]);
    }

    public function isCompliant(DataSourceProvider $provider): bool
    {
        return $this->validate($provider)->passed();
    }

    /**
     * The contract-relevant, plain-data description of a definition — what the
     * editor sees and what idempotency/snapshot comparisons use. Never calls fetch().
     *
     * @return array{id: string, label: string, recordKind: string, maxLimit: int, defaultLimit: int, options: list<array<string, mixed>>}
     */
    public static function describe(DataSourceDefinition $definition): array
    {
        $options = [];
        foreach ($definition->options() as $option) {
            $options[] = $option->toArray();
        }

        return [
            'id' => trim($definition->id()),
            'label' => trim($definition->label()),
            'recordKind' => trim($definition->recordKind()),
            'maxLimit' => $definition->maxLimit(),
            'defaultLimit' => $definition->defaultLimit(),
            'options' => $options,
        ];
    }

    private function checkNamespace(DataSourceProvider $provider, bool $internal): DataSourceComplianceCheck
    {
        $ns = $this->safeNamespace($provider);

        if ($ns === '') {
            return DataSourceComplianceCheck::fail('namespace', 'namespace() must be a non-empty string.');
        }

        if ($internal) {
            return $ns === DataSourceIdentifier::RESERVED_NAMESPACE
                ? DataSourceComplianceCheck::pass('namespace')
                : DataSourceComplianceCheck::fail('namespace', 'An internal provider must use the reserved "page-builder" namespace.');
        }

        if ($ns === DataSourceIdentifier::RESERVED_NAMESPACE) {
            return DataSourceComplianceCheck::fail('namespace', 'The "page-builder" namespace is reserved.');
        }

        if (! DataSourceIdentifier::isValidSegment($ns)) {
            return DataSourceComplianceCheck::fail('namespace', 'namespace() must be a valid lowercase plugin slug.');
        }

        return DataSourceComplianceCheck::pass('namespace');
    }

    private function checkVersion(DataSourceProvider $provider): DataSourceComplianceCheck
    {
        try {
            $version = trim($provider->version());
        } catch (Throwable $e) {
            return DataSourceComplianceCheck::fail('version', 'version() threw: '.$e->getMessage());
        }

        return $version === '' || strlen($version) > 32
            ? DataSourceComplianceCheck::fail('version', 'version() must be a non-empty string of at most 32 characters.')
            : DataSourceComplianceCheck::pass('version');
    }

    private function checkSources(DataSourceProvider $provider): DataSourceComplianceCheck
    {
        try {
            $count = 0;
            foreach ($provider->sources() as $source) {
                if (! $source instanceof DataSourceDefinition) {
                    return DataSourceComplianceCheck::fail('sources', 'Every source must implement DataSourceDefinition.');
                }
                $count++;
            }
        } catch (Throwable $e) {
            return DataSourceComplianceCheck::fail('sources', 'sources() threw: '.$e->getMessage());
        }

        if ($count === 0) {
            return DataSourceComplianceCheck::fail('sources', 'sources() must contribute at least one source.');
        }

        if ($count > DataSourceLimits::MAX_SOURCES_PER_PROVIDER) {
            return DataSourceComplianceCheck::fail('sources', 'A provider may register at most '.DataSourceLimits::MAX_SOURCES_PER_PROVIDER.' sources.');
        }

        return DataSourceComplianceCheck::pass('sources');
    }

    private function checkIdentifiers(DataSourceProvider $provider, bool $internal): DataSourceComplianceCheck
    {
        $ns = $this->safeNamespace($provider);

        foreach ($this->definitions($provider) as $definition) {
            $id = $this->safeString(fn (): string => $definition->id());

            if (! DataSourceIdentifier::isValid($id)) {
                return DataSourceComplianceCheck::fail('identifiers', 'Source id "'.$id.'" is not a canonical "provider:local" identifier.');
            }

            $parsed = DataSourceIdentifier::parse($id);

            if ($parsed->namespace !== $ns) {
                return DataSourceComplianceCheck::fail('identifiers', 'Source id "'.$id.'" must use the provider namespace "'.$ns.'".');
            }

            if (! $internal && $parsed->isReservedNamespace()) {
                return DataSourceComplianceCheck::fail('identifiers', 'Source id "'.$id.'" claims the reserved namespace.');
            }
        }

        return DataSourceComplianceCheck::pass('identifiers');
    }

    private function checkDuplicates(DataSourceProvider $provider): DataSourceComplianceCheck
    {
        $seen = [];

        foreach ($this->definitions($provider) as $definition) {
            $id = $this->safeString(fn (): string => $definition->id());

            if (isset($seen[$id])) {
                return DataSourceComplianceCheck::fail('duplicates', 'Source id "'.$id.'" is declared more than once.');
            }
            $seen[$id] = true;
        }

        return DataSourceComplianceCheck::pass('duplicates');
    }

    private function checkRecordKinds(DataSourceProvider $provider): DataSourceComplianceCheck
    {
        foreach ($this->definitions($provider) as $definition) {
            $kind = $this->safeString(fn (): string => $definition->recordKind());

            if (! DataSourceIdentifier::isValidSegment($kind)) {
                return DataSourceComplianceCheck::fail('record_kinds', 'recordKind() must be a lowercase slug.');
            }
        }

        return DataSourceComplianceCheck::pass('record_kinds');
    }

    private function checkLimits(DataSourceProvider $provider): DataSourceComplianceCheck
    {
        foreach ($this->definitions($provider) as $definition) {
            try {
                $max = $definition->maxLimit();
                $default = $definition->defaultLimit();
            } catch (Throwable $e) {
                return DataSourceComplianceCheck::fail('limits', 'limit methods threw: '.$e->getMessage());
            }

            if ($max < 1 || $max > DataSourceLimits::MAX_ITEMS) {
                return DataSourceComplianceCheck::fail('limits', 'maxLimit() must be between 1 and '.DataSourceLimits::MAX_ITEMS.'.');
            }

            if ($default < 1 || $default > $max) {
                return DataSourceComplianceCheck::fail('limits', 'defaultLimit() must be between 1 and maxLimit().');
            }
        }

        return DataSourceComplianceCheck::pass('limits');
    }

    private function checkLabels(DataSourceProvider $provider): DataSourceComplianceCheck
    {
        foreach ($this->definitions($provider) as $definition) {
            if (! $this->validLabel($this->safeString(fn (): string => $definition->label()))) {
                return DataSourceComplianceCheck::fail('labels', 'label() must be a non-empty string of at most '.DataSourceLimits::MAX_LABEL_LENGTH.' characters.');
            }
        }

        return DataSourceComplianceCheck::pass('labels');
    }

    private function checkOptions(DataSourceProvider $provider): DataSourceComplianceCheck
    {
        foreach ($this->definitions($provider) as $definition) {
            try {
                $options = $definition->options();
            } catch (Throwable $e) {
                return DataSourceComplianceCheck::fail('options', 'options() threw: '.$e->getMessage());
            }

            if (! is_array($options) || ! array_is_list($options) || count($options) > DataSourceLimits::MAX_OPTIONS) {
                return DataSourceComplianceCheck::fail('options', 'options() must be a list of at most '.DataSourceLimits::MAX_OPTIONS.' options.');
            }

            $keys = [];
            foreach ($options as $option) {
                $failure = $this->optionFailure($option, $keys);

                if ($failure !== null) {
                    return DataSourceComplianceCheck::fail('options', $failure);
                }
            }
        }

        return DataSourceComplianceCheck::pass('options');
    }

    /**
     * @param  array<string, true>  $keys
     */
    private function optionFailure(mixed $option, array &$keys): ?string
    {
        if (! $option instanceof DataSourceOption) {
            return 'Every option must be a DataSourceOption.';
        }

        if (preg_match(DataSourceLimits::KEY_PATTERN, $option->key) !== 1) {
            return 'Option key "'.$option->key.'" must be a snake_case identifier.';
        }

        if (isset($keys[$option->key])) {
            return 'Option key "'.$option->key.'" is declared more than once.';
        }
        $keys[$option->key] = true;

        if (! $this->validLabel($option->label)) {
            return 'Option "'.$option->key.'" needs a bounded label.';
        }

        if ($option->type === DataSourceOption::TYPE_BOOLEAN) {
            return is_bool($option->default) ? null : 'Boolean option "'.$option->key.'" needs a boolean default.';
        }

        $count = count($option->choices);
        if ($count < 1 || $count > DataSourceLimits::MAX_CHOICES) {
            return 'Enum option "'.$option->key.'" needs 1..'.DataSourceLimits::MAX_CHOICES.' choices.';
        }

        foreach ($option->choices as $value => $label) {
            if (! DataSourceIdentifier::isValidSegment((string) $value) || ! is_string($label) || ! $this->validLabel($label)) {
                return 'Enum option "'.$option->key.'" has an invalid choice.';
            }
        }

        return $option->accepts($option->default) ? null : 'Enum option "'.$option->key.'" default must be one of its choices.';
    }

    private function checkDeterminism(DataSourceProvider $provider): DataSourceComplianceCheck
    {
        try {
            $first = $this->descriptions($provider);
            $second = $this->descriptions($provider);
        } catch (Throwable $e) {
            return DataSourceComplianceCheck::fail('determinism', 'sources() could not be described: '.$e->getMessage());
        }

        return $first === $second
            ? DataSourceComplianceCheck::pass('determinism')
            : DataSourceComplianceCheck::fail('determinism', 'sources() must return the same definitions on every call.');
    }

    private function checkIsolation(DataSourceProvider $provider): DataSourceComplianceCheck
    {
        return $provider instanceof DataSourceProviderRegistry
            ? DataSourceComplianceCheck::fail('isolation', 'A provider must not be the registry itself.')
            : DataSourceComplianceCheck::pass('isolation');
    }

    /** @return list<array<string, mixed>> */
    private function descriptions(DataSourceProvider $provider): array
    {
        $out = [];
        foreach ($this->definitions($provider) as $definition) {
            $out[] = self::describe($definition);
        }

        usort($out, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        return $out;
    }

    /** @return list<DataSourceDefinition> */
    private function definitions(DataSourceProvider $provider): array
    {
        try {
            $out = [];
            foreach ($provider->sources() as $source) {
                if ($source instanceof DataSourceDefinition) {
                    $out[] = $source;
                }
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    private function validLabel(string $label): bool
    {
        $label = trim($label);

        return $label !== '' && mb_strlen($label) <= DataSourceLimits::MAX_LABEL_LENGTH;
    }

    private function safeNamespace(DataSourceProvider $provider): string
    {
        return $this->safeString(fn (): string => $provider->namespace());
    }

    /** @param  callable(): string  $read */
    private function safeString(callable $read): string
    {
        try {
            return trim($read());
        } catch (Throwable) {
            return '';
        }
    }
}
