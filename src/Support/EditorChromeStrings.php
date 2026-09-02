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
            // PB-TREG-2 — provenance recovery messages, keyed by the server machine code so
            // the Vue editor (no client i18n) can surface an actionable, localized message
            // without decoding tokens. Never expose signatures/keys/paths.
            'pb_provenance_invalid' => $t('This inserted design could not be verified. Please re-open the resource browser and insert it again.'),
            'pb_provenance_unmatched' => $t('This inserted design changed before saving and can no longer be attributed. Please re-insert it.'),
            'pb_provenance_conflict' => $t('This design has conflicting attribution and was not saved. Please re-insert it.'),
            'pb_provenance_limit' => $t('Please save your draft before inserting more designs.'),
            'pb_provenance_expired' => $t('This inserted design expired before saving; revalidating…'),
            'pb_provenance_horizon' => $t('This inserted design can no longer be revalidated. Re-insert it to keep its attribution.'),
            'pb_provenance_rebind_required' => $t('Part of an inserted design was removed; updating its attribution…'),
            'pb_provenance_renewal_failed' => $t('This inserted design could not be revalidated. Your edits are kept; please re-insert it.'),
            'pb_provenance_rebind_failed' => $t('This inserted design could not be updated. Your edits are kept; please re-insert it.'),
            'pb_provenance_discard_confirm' => $t('Remove the attribution for this inserted design?'),
            'pb_insert_failed' => $t('This design could not be inserted. Please try again.'),
            'pb_insert_stale' => $t('This page changed in another session. Please reload to continue.'),
        ];
    }
}
