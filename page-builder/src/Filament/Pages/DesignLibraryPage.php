<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;

/**
 * Design Library admin entry (Plugins → Design Library).
 *
 * Phase 26.47A migrates the former "Template Library" nav entry into the Design
 * Library product (Phases 26.40–26.47). The nav item opens the repository-backed
 * Design Library surface served by DesignLibraryController at `page-builder/library`,
 * where users discover Design Resources (blocks, sections, full pages) rather than
 * templates. Everything visible there comes from the DesignResourceRepository and
 * previews render through the Runtime Preview Engine.
 *
 * This page carries no runtime, command, or BuilderState logic — it is purely the
 * navigation gateway. Access is gated by `page-builder.view`.
 */
class DesignLibraryPage extends Page
{
    protected static ?string $slug = 'page-builder/design-library';

    protected static string|\UnitEnum|null $navigationGroup = 'Plugins';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Design Library';

    protected string $view = 'page-builder::filament.pages.design-library';

    public static function canAccess(): bool
    {
        return cms_can('page-builder.view');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return cms_can('page-builder.view');
    }

    public static function getNavigationLabel(): string
    {
        return tn_trans('Design Library');
    }

    /**
     * The nav item opens the repository-backed Design Library page directly, so the
     * user lands on the resource discovery surface rather than this gateway page.
     */
    public static function getNavigationUrl(): string
    {
        return self::designLibraryUrl();
    }

    public function getTitle(): string
    {
        return tn_trans('Design Library');
    }

    public function getSubheading(): ?string
    {
        return tn_trans('Browse, preview, import and export reusable design resources — blocks, sections and full pages.');
    }

    /**
     * URL of the repository-backed Design Library surface. Falls back to the raw
     * path when the named route is not registered (e.g. during isolated tests).
     */
    public static function designLibraryUrl(): string
    {
        try {
            return route('page-builder.library');
        } catch (\Throwable) {
            return url('page-builder/library');
        }
    }
}
