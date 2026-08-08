<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Plugins\PageBuilder\LayoutTranslation\LayoutTranslationSetting;
use TheNguyen\CMS\Localization\Contracts\LanguageConfigurationContract;

/**
 * Plugins → Page Builder Settings (LT-M1 productization).
 *
 * The user-facing home for Page Builder runtime configuration. Today it owns one
 * control — Layout Translation (Auto / Enabled / Disabled) — persisted in the
 * existing cms_settings store under LayoutTranslationSetting::SETTING_KEY. No
 * .env edit is required: the service provider resolves this setting (plus an
 * optional developer override and the Core enabled-locale count for Auto) into
 * the one effective config('page-builder.translation.enabled') flag every reader
 * uses. Access is gated by `page-builder.edit`.
 */
class PageBuilderSettingsPage extends Page
{
    protected static ?string $slug = 'page-builder/settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Plugins';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 3;

    protected string $view = 'page-builder::filament.pages.settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return cms_can('page-builder.edit');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return cms_can('page-builder.edit');
    }

    public static function getNavigationLabel(): string
    {
        return tn_trans('Page Builder Settings');
    }

    public function getTitle(): string
    {
        return tn_trans('Page Builder Settings');
    }

    public function mount(): void
    {
        $this->data = [
            'layout_translation' => LayoutTranslationSetting::normalizeMode(
                settings(LayoutTranslationSetting::SETTING_KEY, LayoutTranslationSetting::MODE_AUTO),
            ),
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('layout_translation')
                    ->label(tn_trans('Layout Translation'))
                    ->options([
                        LayoutTranslationSetting::MODE_AUTO => tn_trans('Auto (recommended)'),
                        LayoutTranslationSetting::MODE_ENABLED => tn_trans('Enabled'),
                        LayoutTranslationSetting::MODE_DISABLED => tn_trans('Disabled'),
                    ])
                    ->default(LayoutTranslationSetting::MODE_AUTO)
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->required()
                    ->helperText(tn_trans('Auto enables layout translation when more than one language is active in Languages. Enabled forces it on; Disabled keeps the legacy shared-layout behaviour. No .env edit is required.')),

                Placeholder::make('layout_translation_state')
                    ->label(tn_trans('Current effective state'))
                    ->content(fn (): HtmlString => $this->effectiveStateHtml()),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $mode = LayoutTranslationSetting::normalizeMode(
            $this->form->getState()['layout_translation'] ?? LayoutTranslationSetting::MODE_AUTO,
        );

        $settings = app('cms.settings');
        $settings->set(LayoutTranslationSetting::SETTING_KEY, $mode, 'string', [
            'is_public' => false,
            'autoload' => true,
        ]);
        $settings->clearCache();

        Notification::make()
            ->title(tn_trans('Page Builder settings saved'))
            ->success()
            ->send();
    }

    /**
     * Live effective state (recomputed from the current setting + override +
     * locale count), so the hint is accurate immediately after saving even though
     * config('page-builder.translation.enabled') was resolved earlier this request.
     */
    private function effectiveStateHtml(): HtmlString
    {
        $override = LayoutTranslationSetting::normalizeOverride(
            config('page-builder.translation.override'),
        );

        $mode = LayoutTranslationSetting::normalizeMode(
            settings(LayoutTranslationSetting::SETTING_KEY, LayoutTranslationSetting::MODE_AUTO),
        );

        $count = 0;
        try {
            $count = count(app(LanguageConfigurationContract::class)->enabledLocales());
        } catch (\Throwable) {
            // Locale authority unavailable: fall through to the Auto/legacy result.
        }

        $enabled = LayoutTranslationSetting::decide($override, $mode, $count);
        $label = $enabled ? tn_trans('Layout Translation is ON') : tn_trans('Layout Translation is OFF');
        $color = $enabled ? '#16a34a' : '#64748b';

        $suffix = $override !== null
            ? ' '.tn_trans('(forced by the PAGE_BUILDER_TRANSLATION developer override)')
            : '';

        return new HtmlString(
            '<span style="font-weight:600;color:'.$color.';">'.e($label).'</span>'.e($suffix),
        );
    }
}
