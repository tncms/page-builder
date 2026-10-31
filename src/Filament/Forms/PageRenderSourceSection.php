<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Filament\Forms;

use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Plugins\PageBuilder\Authority\AuthorityResponse;
use Plugins\PageBuilder\Authority\PageBuilderAuthorityException;
use Plugins\PageBuilder\Editor\Translation\EditorOwnerResolutionException;
use Plugins\PageBuilder\Editor\Translation\EffectiveEditorOwnerResolver;
use Plugins\PageBuilder\Support\EditorModeSwitcher;
use Plugins\PageBuilder\Support\LayoutStore;
use Plugins\PageBuilder\Support\LayoutVersionConflictException;
use Plugins\PageBuilder\Support\RenderSource;
use Plugins\PageBuilder\Support\RenderSourceWriter;
use TheNguyen\CMS\ContentAuthority\ContentWriteException;
use TheNguyen\CMS\Models\Content;

/**
 * The "Rendering" control on the Page and Post edit forms (PB-RS-1; Post parity
 * PB-CORE-AUTHORITY-REMEDIATION-R1).
 *
 * It shows which source renders publicly for the EFFECTIVE owner in the editing
 * locale — Classic Content or Page Builder Content (ADR-PB-RENDER-SOURCE-001) — and
 * switches it only through an explicit confirmation that states the current source,
 * the target, what will render publicly and that the other source is kept. The
 * write goes through {@see EditorModeSwitcher}: authorized, checked against the Core
 * revision this form was opened with and the layout publication token captured when
 * the form loaded (a stale form is refused with zero writes).
 *
 * Injected additively through the `cms.form.schema.{type}` hooks; no app/core
 * resource is modified. Nothing here is dehydrated into the Content record, so the
 * Classic Editor save path and any unsaved form edits are untouched by a switch.
 */
final class PageRenderSourceSection
{
    public static function make(string $ownerType = 'page'): Section
    {
        return Section::make(__('Rendering'))
            ->visible(static fn (?Content $record): bool => $record !== null
                && (string) $record->getAttribute('type') === $ownerType
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
                    // Status only: the switch is the confirmed action below.
                    ->disabled()
                    // Not a Content attribute — never written to the content record on save.
                    ->dehydrated(false)
                    // PB-STATE-2A — hydrate the persisted effective Render Source on
                    // EDIT forms (record-data fill skips default()), read-only.
                    ->afterStateHydrated(static function (Radio $component, ?Content $record): void {
                        $value = self::hydratedStateFor($record);

                        if ($value !== null) {
                            $component->state($value);
                        }
                    }),

                // The public-state token this form was opened with (mode + published
                // payload). Compared on switch so a stale form never overrides a newer
                // switch or publication made elsewhere.
                Hidden::make('pb_render_source_token')
                    ->dehydrated(false)
                    ->afterStateHydrated(static function (Hidden $component, ?Content $record): void {
                        $component->state(self::publicationTokenFor($record));
                    }),

                // No translation in the form's locale yet (Core creates it on save): there is
                // no rendering to choose for it, so the switch is not offered.
                Placeholder::make('pb_render_source_unsaved_note')
                    ->hiddenLabel()
                    ->visible(static fn (?Content $record, mixed $livewire): bool => $record !== null
                        && ! self::canSwitchFor($record, self::locale($livewire)))
                    ->content(__('Save this translation first. Its public rendering can be chosen once it exists.')),

                Actions::make([
                    Action::make('pbSwitchRenderSource')
                        ->visible(static fn (?Content $record, mixed $livewire): bool => $record !== null
                            && self::canSwitchFor($record, self::locale($livewire)))
                        ->label(static fn (Get $get): string => self::targetFor($get('pb_render_source')) === RenderSource::Builder
                            ? __('Switch to Page Builder content')
                            : __('Switch to Classic content'))
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalIcon('heroicon-o-arrows-right-left')
                        ->modalHeading(__('Change what renders publicly?'))
                        ->modalDescription(static fn (Get $get): string => self::confirmationText(self::targetFor($get('pb_render_source'))))
                        ->modalSubmitActionLabel(static fn (Get $get): string => self::targetFor($get('pb_render_source')) === RenderSource::Builder
                            ? __('Switch to Page Builder content')
                            : __('Switch to Classic content'))
                        ->modalCancelActionLabel(__('Cancel'))
                        ->action(static function (Get $get, Set $set, ?Content $record, mixed $livewire): void {
                            if ($record === null) {
                                return;
                            }

                            $token = $get('pb_render_source_token');
                            self::performSwitch(
                                $record,
                                self::targetFor($get('pb_render_source')),
                                self::formRevision($livewire, $record),
                                is_string($token) ? $token : null,
                                self::locale($livewire),
                            );

                            // Re-read the persisted state (the truth after success or refusal).
                            $set('pb_render_source', self::hydratedStateFor($record));
                            $set('pb_render_source_token', self::publicationTokenFor($record));
                        }),
                ]),

                // Shown only when Page Builder Content is selected.
                Placeholder::make('pb_render_source_builder_note')
                    ->hiddenLabel()
                    ->visible(static fn (Get $get): bool => $get('pb_render_source') === RenderSource::Builder->value)
                    ->content(__('Builder content becomes public only after Publish. Draft changes remain private.')),
            ]);
    }

    /**
     * Perform a confirmed switch and notify the outcome. Returns whether it was
     * written; every refusal is a notification with zero writes. $locale is the
     * locale the form was opened with (defaults to the current editing locale).
     */
    public static function performSwitch(Content $record, RenderSource $target, ?int $formRevision, ?string $publicationToken, ?string $locale = null): bool
    {
        try {
            app(EditorModeSwitcher::class)->switch($record, $locale ?? self::locale(), $target, auth()->user(), $formRevision, $publicationToken);
        } catch (LayoutVersionConflictException) {
            self::notifyRefusal(__('This page changed in another session. Reload it before switching the editor mode.'));

            return false;
        } catch (EditorOwnerResolutionException|PageBuilderAuthorityException|ContentWriteException $e) {
            self::notifyRefusal(match (true) {
                $e instanceof EditorOwnerResolutionException && $e->reason === 'translation_not_found' => __('Save this translation first. Its public rendering can be chosen once it exists.'),
                $e instanceof EditorOwnerResolutionException => $e->getMessage(),
                default => AuthorityResponse::message($e),
            });

            return false;
        }

        Notification::make()
            ->title(__('Rendering source updated'))
            ->body($target === RenderSource::Builder
                ? __('Page Builder content now renders publicly once it is published. The classic content is kept.')
                : __('Classic content now renders publicly. The Page Builder layout is kept.'))
            ->success()
            ->send();

        return true;
    }

    /**
     * The value to hydrate into the status radio on form load, or null to defer to
     * default() (PB-STATE-2A). Read-only and side-effect free.
     */
    public static function hydratedStateFor(?Content $record): ?string
    {
        if ($record === null) {
            return null;
        }

        return self::current($record)->value;
    }

    /** The publication token of the effective owner in the editing locale, read-only. */
    public static function publicationTokenFor(?Content $record): ?string
    {
        if ($record === null) {
            return null;
        }

        [$ownerType, $ownerId] = app(EffectiveEditorOwnerResolver::class)
            ->resolveForReadOrCanonical((string) $record->getAttribute('type'), $record->getKey(), self::locale());

        return app(LayoutStore::class)->currentPublicationToken($ownerType, $ownerId);
    }

    /** The confirmation body: current → target, what renders publicly, what is kept. */
    public static function confirmationText(RenderSource $target): string
    {
        return $target === RenderSource::Builder
            ? __('Currently the classic content renders publicly. After switching, the published Page Builder layout renders instead; while no layout is published, visitors keep seeing the classic content. The classic content is kept unchanged and you can switch back at any time. Unsaved changes in this form are not affected — save the form separately.')
            : __('Currently the Page Builder layout renders publicly. After switching, the classic content renders instead. The Page Builder layout (draft and published) is kept unchanged and you can switch back at any time. Unsaved changes in this form are not affected — save the form separately.');
    }

    /** The source the action switches TO, from the displayed current source. */
    private static function targetFor(mixed $current): RenderSource
    {
        return RenderSource::fromStorage(is_string($current) ? $current : null) === RenderSource::Builder
            ? RenderSource::Classic
            : RenderSource::Builder;
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
            (string) $record->getAttribute('type'),
            $record->getKey(),
            self::locale(),
        );
    }

    /**
     * Whether $locale has a layout owner whose rendering can be switched: false while
     * the form edits a translation Core has not created yet. Read-only.
     */
    public static function canSwitchFor(Content $record, ?string $locale): bool
    {
        try {
            app(EffectiveEditorOwnerResolver::class)->resolve((string) $record->getAttribute('type'), $record->getKey(), $locale);
        } catch (EditorOwnerResolutionException) {
            return false;
        }

        return true;
    }

    /**
     * The locale being edited in the admin form, or null: the Core edit page's pinned
     * `translationLocale` (Livewire commits carry no `?locale=`), else the current
     * editing locale.
     */
    private static function locale(mixed $livewire = null): ?string
    {
        if (is_object($livewire) && property_exists($livewire, 'translationLocale')
            && is_string($livewire->translationLocale) && $livewire->translationLocale !== '') {
            return $livewire->translationLocale;
        }

        return function_exists('editing_locale') ? editing_locale() : null;
    }

    /**
     * The Core revision the edit form was opened with: the Core admin page's locked
     * `contentRevision` (Core 1.0.0-beta.7.1.37+), else the record as loaded.
     */
    private static function formRevision(mixed $livewire, Content $record): int
    {
        if (is_object($livewire) && property_exists($livewire, 'contentRevision') && is_int($livewire->contentRevision)) {
            return $livewire->contentRevision;
        }

        return (int) $record->getAttribute('revision');
    }

    private static function notifyRefusal(string $message): void
    {
        Notification::make()
            ->title(__('Could not update the rendering source.'))
            ->body($message)
            ->danger()
            ->send();
    }
}
