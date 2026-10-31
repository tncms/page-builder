<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * Admin-UI-locale chrome strings for the authoring editors (PB-TEXT-AUTHORING-R1):
 * the visual Text editor (`richtext`) and the Custom HTML source editor
 * (`htmlsource`). The editor Vue has no client i18n, so these are stamped into each
 * such field's `editor.strings` when the editor metadata is published, exactly like
 * the data-source field chrome. Labels only; they never touch node data.
 */
final class AuthoringFieldStrings
{
    private const TYPES = ['richtext', 'htmlsource'];

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<int, array<string, mixed>>
     */
    public static function enrich(array $definitions): array
    {
        $strings = self::strings();

        return array_map(static function (array $definition) use ($strings): array {
            foreach (is_array($definition['schema']['tabs'] ?? null) ? $definition['schema']['tabs'] : [] as $t => $tab) {
                foreach (is_array($tab['fields'] ?? null) ? $tab['fields'] : [] as $f => $entry) {
                    if (is_array($entry) && in_array($entry['type'] ?? null, self::TYPES, true)) {
                        $editor = is_array($entry['editor'] ?? null) ? $entry['editor'] : [];
                        $editor['strings'] = $strings;
                        $definition['schema']['tabs'][$t]['fields'][$f]['editor'] = $editor;
                    }
                }
            }

            return $definition;
        }, $definitions);
    }

    /** @return array<string, string> */
    private static function strings(): array
    {
        $locale = CatalogLocale::admin();
        $t = static fn (string $key): string => (string) __($key, [], $locale);

        return [
            'toolbar' => $t('Text formatting'),
            'editor' => $t('Text editor'),
            'bold' => $t('Bold'),
            'italic' => $t('Italic'),
            'underline' => $t('Underline'),
            'bulleted' => $t('Bulleted list'),
            'numbered' => $t('Numbered list'),
            'quote' => $t('Quote'),
            'code' => $t('Inline code'),
            'codeBlock' => $t('Code block'),
            'link' => $t('Link'),
            'linkUrl' => $t('Link address'),
            'linkApply' => $t('Apply link'),
            'linkRemove' => $t('Remove link'),
            'linkNewTab' => $t('Open in a new tab'),
            'linkInvalid' => $t('Use an address that starts with https://, http://, mailto:, tel:, / or #.'),
            'clear' => $t('Remove formatting'),
            'undo' => $t('Undo'),
            'redo' => $t('Redo'),
            'tooLarge' => $t('This content is too large or too deeply nested. Shorten it to save.'),
            'htmlSource' => $t('HTML source'),
            'htmlHelp' => $t('Custom HTML is cleaned before it is shown: scripts, styles, forms, embeds, event attributes, unsafe links and images from other sites are removed. The canvas shows the cleaned result.'),
            'htmlEmpty' => $t('No HTML yet. Type or paste HTML source here.'),
        ];
    }
}
