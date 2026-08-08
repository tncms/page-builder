<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Plugins\PageBuilder\Facades\PageBuilder;
use Plugins\PageBuilder\Support\PageBuilderManager;

/**
 * Page Builder admin dashboard (Plugins → Page Builder), mounted by the panel
 * at /{admin}/page-builder.
 *
 * Phase 1 is a read-only status screen: it reports whether the builder is
 * enabled and how many section / block types are registered. The heavy Vue
 * editor is a later phase and is intentionally absent. Access is gated by the
 * `page-builder.view` permission.
 */
class PageBuilderDashboardPage extends Page
{
    protected static ?string $slug = 'page-builder';

    protected static string|\UnitEnum|null $navigationGroup = 'Plugins';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $title = 'Page Builder';

    protected string $view = 'page-builder::filament.pages.dashboard';

    public static function canAccess(): bool
    {
        return cms_can('page-builder.view');
    }

    public static function getNavigationLabel(): string
    {
        return tn_trans('Page Builder');
    }

    public function getTitle(): string
    {
        return tn_trans('Page Builder');
    }

    public function getSubheading(): ?string
    {
        return tn_trans('Structured, block-based page builder.')
            .' (v'.PageBuilderManager::VERSION.')';
    }

    /**
     * Status snapshot for the view (enabled flag + registered counts/types).
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return PageBuilder::status();
    }
}
