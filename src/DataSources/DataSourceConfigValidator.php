<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

/**
 * The ONE server-side gate for a component field's saved data-source
 * configuration (PB-XREG-2). Used identically by the save path, the editor
 * preview, and the public render — the browser only transports the value.
 *
 * Persisted shape (inside the existing node `data` container, no new root key):
 *
 *     {"source": "ecommerce:latest-products", "limit": 8, "options": {"in_stock_only": true}}
 *
 * Fail-closed rule: any key other than `source`, `limit`, `options` is REJECTED
 * (never silently stripped), as is any option key the source did not declare.
 * Validation is deterministic and locale-independent.
 *
 * Structural rules always apply. Source-specific rules (record kind, limit
 * ceiling, option allow-list) apply only when the source is KNOWN; a well-formed
 * but unregistered source resolves to `unavailable` so the saved value survives
 * provider deactivation untouched.
 */
final class DataSourceConfigValidator
{
    public const REASON_MALFORMED = 'malformed';

    public const REASON_TOO_LARGE = 'too_large';

    public const REASON_UNKNOWN_KEY = 'unknown_key';

    public const REASON_INVALID_SOURCE = 'invalid_source';

    public const REASON_INCOMPATIBLE_SOURCE = 'incompatible_source';

    public const REASON_INVALID_LIMIT = 'invalid_limit';

    public const REASON_INVALID_OPTION = 'invalid_option';

    private const ALLOWED_KEYS = ['source', 'limit', 'options'];

    public function __construct(private readonly DataSourceProviderRegistry $registry) {}

    /**
     * @param  list<string>  $acceptedKinds  record kinds the field accepts
     */
    public function inspect(mixed $value, array $acceptedKinds): DataSourceConfigResolution
    {
        if ($value === null || $value === []) {
            return DataSourceConfigResolution::empty();
        }

        if (! is_array($value) || array_is_list($value)) {
            return DataSourceConfigResolution::invalid(self::REASON_MALFORMED);
        }

        $encoded = json_encode($value);
        if ($encoded === false || strlen($encoded) > DataSourceLimits::MAX_CONFIG_BYTES) {
            return DataSourceConfigResolution::invalid(self::REASON_TOO_LARGE);
        }

        foreach (array_keys($value) as $key) {
            if (! in_array($key, self::ALLOWED_KEYS, true)) {
                return DataSourceConfigResolution::invalid(self::REASON_UNKNOWN_KEY);
            }
        }

        $source = $value['source'] ?? '';
        if (! is_string($source)) {
            return DataSourceConfigResolution::invalid(self::REASON_INVALID_SOURCE);
        }

        $source = trim($source);
        if ($source === '') {
            return DataSourceConfigResolution::empty();
        }

        if (! DataSourceIdentifier::isValid($source)) {
            return DataSourceConfigResolution::invalid(self::REASON_INVALID_SOURCE, $source);
        }

        $limit = $value['limit'] ?? null;
        if ($limit !== null && ! is_int($limit)) {
            return DataSourceConfigResolution::invalid(self::REASON_INVALID_LIMIT, $source);
        }

        $options = $value['options'] ?? [];
        if (! is_array($options) || ($options !== [] && array_is_list($options))) {
            return DataSourceConfigResolution::invalid(self::REASON_INVALID_OPTION, $source);
        }

        $definition = $this->registry->definition($source);
        if ($definition === null) {
            return DataSourceConfigResolution::unavailable($source);
        }

        return $this->againstDefinition($source, $definition, $limit, $options, $acceptedKinds);
    }

    /**
     * @param  array<mixed>  $options
     * @param  list<string>  $acceptedKinds
     */
    private function againstDefinition(
        string $source,
        Contracts\DataSourceDefinition $definition,
        ?int $limit,
        array $options,
        array $acceptedKinds,
    ): DataSourceConfigResolution {
        $contract = DataSourceProviderComplianceValidator::describe($definition);

        if (! in_array($contract['recordKind'], $acceptedKinds, true)) {
            return DataSourceConfigResolution::invalid(self::REASON_INCOMPATIBLE_SOURCE, $source);
        }

        $limit ??= $contract['defaultLimit'];
        $ceiling = min($contract['maxLimit'], DataSourceLimits::MAX_ITEMS);
        if ($limit < 1 || $limit > $ceiling) {
            return DataSourceConfigResolution::invalid(self::REASON_INVALID_LIMIT, $source);
        }

        $declared = [];
        foreach ($definition->options() as $option) {
            $declared[$option->key] = $option;
        }

        $resolved = [];
        foreach ($options as $key => $optionValue) {
            $option = is_string($key) ? ($declared[$key] ?? null) : null;

            if ($option === null || ! $option->accepts($optionValue)) {
                return DataSourceConfigResolution::invalid(self::REASON_INVALID_OPTION, $source);
            }

            $resolved[$key] = $optionValue;
        }

        foreach ($declared as $key => $option) {
            $resolved[$key] ??= $option->default;
        }

        ksort($resolved);

        return DataSourceConfigResolution::valid($source, $definition, new DataSourceQuery($limit, $resolved));
    }
}
