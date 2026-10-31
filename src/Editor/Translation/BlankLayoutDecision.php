<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Editor\Translation;

use Plugins\PageBuilder\Support\EditableOwners;
use Plugins\PageBuilder\Support\EditorUrls;
use TheNguyen\CMS\Models\Content;

/**
 * The blank-layout decision presented when a translation's source language has no
 * Page Builder content to copy (PB-CORE-AUTHORITY-REMEDIATION-R1, C).
 *
 * Three explicit choices, all server-described:
 *  - `blank`  — create the blank translated layout (an explicit POST, `layout=blank`);
 *  - `return` — open the Core content editor in the source locale (writes nothing);
 *  - `cancel` — stay where the user was (writes nothing).
 */
final class BlankLayoutDecision
{
    public const CHOICES = ['blank', 'return', 'cancel'];

    public static function isDecision(EditorOwnerResolutionException $e): bool
    {
        return $e->reason === 'pb_blank_layout_decision_required';
    }

    /**
     * @return array{source_layout: string, locale: string, choices: list<string>, return_url: ?string}
     */
    public static function payload(EditorOwnerResolutionException $e, string $ownerType, Content $owner, string $sourceLocale): array
    {
        return [
            'source_layout' => (string) ($e->data['source_layout'] ?? SourceLayoutInspector::ABSENT),
            'locale' => (string) ($e->data['locale'] ?? ''),
            'choices' => self::CHOICES,
            'return_url' => EditorUrls::withLocale(EditableOwners::editUrl($ownerType, $owner), $sourceLocale),
        ];
    }
}
