<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use Plugins\PageBuilder\Components\ComponentProviderRegistry;
use Plugins\PageBuilder\Origins\ProviderOrigin;

/**
 * PB-XREG-4 — additive editor metadata for component definitions:
 *
 *  - `origin` `{kind: theme, slug}` on theme definitions, server-derived by the component registry, so the palette
 *    can say a component comes from the active theme without exposing paths, classes or services;
 *  - Admin UI locale labels for definitions that carry a per-locale label map (`editor.labels`, theme components),
 *    on the definition and on its fields. Definitions without a map are returned untouched.
 *
 * Save/render authority is unchanged: this only decorates the read-only editor bootstrap.
 */
final class EditorDefinitionOrigins
{
    /**
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<int, array<string, mixed>>
     */
    public static function enrich(array $definitions, ?ComponentProviderRegistry $registry, ?string $locale, ?string $fallback): array
    {
        return array_map(static function (array $definition) use ($registry, $locale, $fallback): array {
            // Theme definitions only: built-in and plugin editor metadata stays byte-identical (the unified catalog
            // carries the origin of every item).
            $origin = $registry?->originOf((string) ($definition['type'] ?? ''));
            if ($origin === null || $origin->kind !== ProviderOrigin::THEME) {
                return $definition;
            }

            $definition['origin'] = $origin->toArray();

            $labels = $definition['editor']['labels'] ?? null;
            if (is_array($labels) && $labels !== []) {
                $definition['label'] = self::pick($labels, $locale, $fallback);
                $definition['schema'] = self::localizeSchema($definition['schema'] ?? null, $locale, $fallback);
            }

            return $definition;
        }, $definitions);
    }

    private static function localizeSchema(mixed $schema, ?string $locale, ?string $fallback): mixed
    {
        if (! is_array($schema) || ! is_array($schema['tabs'] ?? null)) {
            return $schema;
        }

        foreach ($schema['tabs'] as $t => $tab) {
            foreach ((array) ($tab['fields'] ?? []) as $f => $field) {
                $labels = is_array($field) ? ($field['editor']['labels'] ?? null) : null;
                if (is_array($labels) && $labels !== []) {
                    $schema['tabs'][$t]['fields'][$f]['label'] = self::pick($labels, $locale, $fallback);
                }
            }
        }

        return $schema;
    }

    /** @param  array<string, string>  $labels */
    private static function pick(array $labels, ?string $locale, ?string $fallback): string
    {
        return (string) ($labels[(string) $locale] ?? $labels[(string) $fallback] ?? $labels[array_key_first($labels)]);
    }
}
