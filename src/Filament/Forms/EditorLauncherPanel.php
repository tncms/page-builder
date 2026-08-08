<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Filament\Forms;

use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;
use Plugins\PageBuilder\Models\PageBuilderLayout;
use Plugins\PageBuilder\Support\EditorUrls;
use Plugins\PageBuilder\Support\LayoutStore;
use TheNguyen\CMS\Models\Content;

/**
 * The editor-mode switch prepended to the top of the Page / Post edit forms
 * (Phase 26.1.2 — Admin Editor UX Refresh).
 *
 * A modern segmented control (Classic Editor | UX Page Builder) that sits
 * directly beneath the page title so the Builder is discoverable immediately,
 * replacing the amber launcher that used to be buried at the bottom of the
 * form. It is injected additively through the `cms.form.schema.{page|post}`
 * hook (prepended to the schema) — no core or app resource is modified.
 *
 * The switch is a pure client-side UX preference (remembered in localStorage);
 * the authoritative editor mode still lives in {@see LayoutStore} and is only
 * changed inside the Builder, so Classic Editor behaviour is untouched. It
 * renders nothing on the create form (no saved record yet) or for users without
 * `page-builder.edit`.
 */
final class EditorLauncherPanel
{
    /** localStorage key for the remembered mode preference (per browser). */
    private const STORAGE_KEY = 'tn-pb-editor-mode';

    /**
     * @param  string  $ownerType  one of EditableOwners::types() ('page'|'post')
     */
    public static function make(string $ownerType): Section
    {
        return Section::make()
            ->visible(static fn (?Content $record): bool => $record !== null
                && function_exists('cms_can')
                && cms_can('page-builder.edit'))
            ->columnSpanFull()
            ->schema([
                Placeholder::make('pb_editor_mode_switch')
                    ->hiddenLabel()
                    ->content(static fn (?Content $record): HtmlString => new HtmlString(
                        view(
                            'page-builder::filament.forms.editor-mode-switch',
                            self::viewData($ownerType, $record),
                        )->render(),
                    )),
            ]);
    }

    /**
     * All data the segmented-control view needs, as a plain array.
     *
     * Kept public + pure so it is trivially testable without rendering Blade or
     * booting a Filament schema. Reads the saved mode / draft / published status
     * from {@see LayoutStore} (never writes).
     *
     * @return array{
     *     ownerType: string,
     *     storageKey: string,
     *     savedMode: string,
     *     urls: array{live: ?string, structure: ?string, preview: ?string},
     *     status: array{hasDraft: bool, hasPublished: bool, lastEditedHuman: ?string}
     * }
     */
    public static function viewData(string $ownerType, ?Content $record): array
    {
        $layout = $record !== null
            ? app(LayoutStore::class)->find($ownerType, $record->getKey())
            : null;

        $savedMode = $layout?->isPageBuilderMode()
            ? PageBuilderLayout::MODE_BUILDER
            : PageBuilderLayout::MODE_CLASSIC;

        // The Builder opens in the locale currently being edited in the admin form
        // (CORE-L10N.1B) so an EN admin lands on the EN layout and a VI admin on the
        // VI layout. The locale is context only — the backend re-resolves the
        // effective owner from the canonical page + this locale (LT-M1B).
        $locale = function_exists('editing_locale') ? editing_locale() : null;

        return [
            'ownerType' => $ownerType,
            'storageKey' => self::STORAGE_KEY,
            'savedMode' => $savedMode,
            'urls' => [
                'live' => $record !== null ? EditorUrls::live($ownerType, $record->getKey(), null, $locale) : null,
                'structure' => $record !== null ? EditorUrls::structure($ownerType, $record->getKey(), null, $locale) : null,
                'preview' => $record !== null ? self::previewUrl($record) : null,
            ],
            'status' => [
                'hasDraft' => $layout?->draft_payload !== null,
                'hasPublished' => (bool) $layout?->hasPublished(),
                'lastEditedHuman' => $layout?->updated_at?->diffForHumans(),
            ],
        ];
    }

    /**
     * The locale-aware Preview URL for the record in the locale currently being
     * edited (CORE-L10N.1B P3.1). Produced by the ONE canonical Core producer
     * (`cms.preview_url`), which owns all localization + signing policy — this
     * panel only reads the editing locale through the approved Core helper and
     * never constructs a localized URL itself. Returns null (never '#') when a
     * preview cannot be produced, so the view renders a disabled action. Guarded
     * so a missing Core binding (isolated tests / plugin inactive) is a no-op.
     */
    private static function previewUrl(Content $record): ?string
    {
        if (! app()->bound('cms.preview_url') || ! function_exists('editing_locale')) {
            return null;
        }

        try {
            return app('cms.preview_url')->forContent($record, editing_locale());
        } catch (\Throwable) {
            return null;
        }
    }
}
