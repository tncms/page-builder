<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * PB-TEMPLATE-REGISTRY-2-D — the editor's server-resolved chrome strings for the
 * unknown-stored-node experience (Canvas placeholder, Navigator badge, Property
 * Inspector read-only panel).
 *
 * The editor Vue has no client i18n; localization is owned by the plugin JSON language
 * files resolved server-side for the ADMIN UI locale (never the editing/frontend locale,
 * INV-R2D-20). Keyed by stable slug so the Vue layer never depends on a translation key.
 * These strings label/reason ONLY — they never carry or mutate node data. A node's `type`,
 * `id`, and `data` remain locale-invariant untrusted declarative values rendered as escaped
 * text by the components.
 */
final class EditorChromeStrings
{
    /**
     * The Admin-UI-locale resolved chrome strings, keyed by stable slug.
     *
     * @return array<string, string>
     */
    public static function resolve(?string $locale = null): array
    {
        $t = static fn (string $key): string => (string) __($key, [], $locale);

        return [
            'unknownHeading' => $t('Component unavailable'),
            'unknownType' => $t('Component type'),
            'unknownPreserved' => $t('This content is preserved and will be saved unchanged.'),
            'unknownReason' => $t('This component is not currently available in this site.'),
            'unknownInspectorNote' => $t('Editing controls will return when this component is available again.'),
            'unknownBadge' => $t('Unavailable'),
            'unknownNodeLabel' => $t('Unavailable component'),
        ];
    }
}
