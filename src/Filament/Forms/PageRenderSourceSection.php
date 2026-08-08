<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Filament\Forms;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Plugins\PageBuilder\Editor\Translation\EditorOwnerResolutionException;
use Plugins\PageBuilder\Support\RenderSource;
use Plugins\PageBuilder\Support\RenderSourceWriter;
use TheNguyen\CMS\Models\Content;

/**
 * The "Rendering" control on the Page edit form (PB-RS-1, Page only).
 *
 * A radio that chooses the public Render Source (ADR-PB-RENDER-SOURCE-001):
 * Classic Content or Page Builder Content. It is the AUTHORITATIVE write — unlike
 * the client-side {@see EditorLauncherPanel} segmented control — persisting the
 * source for the EFFECTIVE owner of the page in the currently-edited locale
 * through {@see RenderSourceWriter} (which orchestrates only existing services).
 *
 * Injected additively through the `cms.form.schema.page` hook; no app/core
 * resource is modified. It renders only on the edit form (a saved record exists)
 * for users holding `page-builder.edit`. The write happens live on toggle and is
 * not dehydrated into the Content record, so the Classic Editor save path is
 * untouched. Page only — Post/CPT are out of scope for this phase.
 */
final class PageRenderSourceSection
{
    /** The Content owner type this section serves. */
    private const OWNER_TYPE = 'page';

    public static function make(): Section
    {
        return Section::make(__('Rendering'))
            ->visible(static fn (?Content $record): bool => $record !== null
                && function_exists('cms_can')
                && cms_can('page-builder.edit'))
            ->columnSpanFull()
            ->schema([
                Radio::make('pb_render_source')
                    ->hiddenLabel()
                    ->options([
                        RenderSource::Classic->value => __('Classic Content'),
                        RenderSource::Builder->value => __('Page Builder Content'),
                    ])
                    ->default(static fn (?Content $record): string => self::current($record)->value)
                    // Not a Content attribute — persisted to pb_layouts on toggle,
                    // never written to the content record on save.
                    ->dehydrated(false)
                    // PB-STATE-2A — hydrate the persisted effective Render Source on
                    // EDIT forms. Filament applies default() only on create/blank fill
                    // (Schema::fill with no record data); on edit, record-data fill
                    // skips default() and nulls non-record fields, so the radio would
                    // render unselected. This read-only hook re-reads the persisted
                    // value for the effective owner in the editing locale through the
                    // existing seam (no write, no owner-resolution duplication).
                    ->afterStateHydrated(static function (Radio $component, ?Content $record): void {
                        $value = self::hydratedStateFor($record);

                        if ($value !== null) {
                            $component->state($value);
                        }
                    })
                    ->live()
                    ->afterStateUpdated(static function (mixed $state, ?Content $record): void {
                        if ($record === null) {
                            return;
                        }

                        // Fail-closed write: on an unresolvable locale context the
                        // writer throws rather than retarget the source owner
                        // (INV-PB-OWNER-05). Surface a message instead of persisting
                        // to the wrong owner or leaking a raw 500 into the form.
                        try {
                            app(RenderSourceWriter::class)->setForCanonical(
                                self::OWNER_TYPE,
                                $record->getKey(),
                                self::locale(),
                                RenderSource::fromStorage(is_string($state) ? $state : null),
                                self::actorId(),
                            );
                        } catch (EditorOwnerResolutionException $e) {
                            Notification::make()
                                ->title(__('Could not update the rendering source.'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // Shown only when Page Builder Content is selected.
                Placeholder::make('pb_render_source_builder_note')
                    ->hiddenLabel()
                    ->visible(static fn (Get $get): bool => $get('pb_render_source') === RenderSource::Builder->value)
                    ->content(__('Builder content becomes public only after Publish. Draft changes remain private.')),
            ]);
    }

    /**
     * The value to hydrate into the radio on form load, or null to defer to
     * default() (PB-STATE-2A).
     *
     * On the EDIT form ($record present) this returns the persisted effective
     * Render Source value ('classic'|'builder'), read-only, failing closed to
     * Classic through {@see current()}. On the CREATE form ($record null) it
     * returns null so the existing default() policy remains authoritative. Pure
     * and side-effect free so the hydration hook never writes.
     */
    public static function hydratedStateFor(?Content $record): ?string
    {
        if ($record === null) {
            return null;
        }

        return self::current($record)->value;
    }

    /**
     * The current persisted Render Source for the effective owner in the editing
     * locale (fails closed to Classic).
     */
    private static function current(?Content $record): RenderSource
    {
        if ($record === null) {
            return RenderSource::Classic;
        }

        return app(RenderSourceWriter::class)->currentForCanonical(
            self::OWNER_TYPE,
            $record->getKey(),
            self::locale(),
        );
    }

    /** The locale currently being edited in the admin form, or null. */
    private static function locale(): ?string
    {
        return function_exists('editing_locale') ? editing_locale() : null;
    }

    private static function actorId(): ?string
    {
        $id = function_exists('auth') ? auth()->id() : null;

        return $id === null ? null : (string) $id;
    }
}
