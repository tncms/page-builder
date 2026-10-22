<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

/**
 * Locates `data-source` fields in a component definition's serialized schema
 * (PB-XREG-2).
 *
 * Fields live in `schema.tabs[].fields[]`, optionally inside display-only groups
 * (`{type: 'group', fields: [...]}`) — both store their value at `data.<name>`,
 * which is the only placement supported. Bounded walk: no recursion beyond one
 * display-group level.
 */
final class DataSourceFields
{
    public const FIELD_TYPE = 'data-source';

    /**
     * @param  array<string, mixed>|null  $definition  a normalized definition array
     * @return array<string, list<string>> field name => accepted record kinds
     */
    public static function in(?array $definition): array
    {
        $tabs = $definition['schema']['tabs'] ?? null;
        if (! is_array($tabs)) {
            return [];
        }

        $fields = [];
        foreach ($tabs as $tab) {
            foreach (is_array($tab['fields'] ?? null) ? $tab['fields'] : [] as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $children = ($entry['type'] ?? null) === 'group' && is_array($entry['fields'] ?? null)
                    ? $entry['fields']
                    : [$entry];

                foreach ($children as $field) {
                    if (is_array($field) && ($field['type'] ?? null) === self::FIELD_TYPE && is_string($field['name'] ?? null)) {
                        $fields[$field['name']] = self::accepts($field);
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * Stamp the compatible-source catalog and localized chrome strings into every
     * `data-source` field of an editor definition array, so the Inspector renders
     * purely from server-published data.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, string>  $strings
     * @return array<string, mixed>
     */
    public static function enrichForEditor(array $definition, DataSourceProviderRegistry $registry, array $strings): array
    {
        if (self::in($definition) === []) {
            return $definition;
        }

        foreach ($definition['schema']['tabs'] as $t => $tab) {
            foreach (is_array($tab['fields'] ?? null) ? $tab['fields'] : [] as $f => $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                if (($entry['type'] ?? null) === 'group' && is_array($entry['fields'] ?? null)) {
                    foreach ($entry['fields'] as $g => $field) {
                        if (is_array($field) && ($field['type'] ?? null) === self::FIELD_TYPE) {
                            $definition['schema']['tabs'][$t]['fields'][$f]['fields'][$g] = self::enrichField($field, $registry, $strings);
                        }
                    }

                    continue;
                }

                if (($entry['type'] ?? null) === self::FIELD_TYPE) {
                    $definition['schema']['tabs'][$t]['fields'][$f] = self::enrichField($entry, $registry, $strings);
                }
            }
        }

        return $definition;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, string>  $strings
     * @return array<string, mixed>
     */
    private static function enrichField(array $field, DataSourceProviderRegistry $registry, array $strings): array
    {
        $editor = is_array($field['editor'] ?? null) ? $field['editor'] : [];
        $editor['sources'] = $registry->catalog(self::accepts($field));
        $editor['maxItems'] = DataSourceLimits::MAX_ITEMS;
        $editor['strings'] = $strings;
        $field['editor'] = $editor;

        return $field;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    private static function accepts(array $field): array
    {
        $accepts = $field['editor']['accepts'] ?? [];
        if (! is_array($accepts)) {
            return [];
        }

        $kinds = array_values(array_filter($accepts, static fn ($k): bool => is_string($k) && DataSourceIdentifier::isValidSegment($k)));

        return array_slice($kinds, 0, DataSourceLimits::MAX_ACCEPTED_KINDS);
    }
}
