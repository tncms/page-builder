<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Plugins\PageBuilder\Editor\Translation\EditorOwnerResolutionException;
use Plugins\PageBuilder\Editor\Translation\EffectiveEditorOwnerResolver;
use Plugins\PageBuilder\Editor\Translation\FirstOpenLayoutProvisioner;
use Plugins\PageBuilder\Facades\PageBuilder;
use Plugins\PageBuilder\Models\PageBuilderLayout;
use Plugins\PageBuilder\Support\EditableOwners;
use Plugins\PageBuilder\Support\LayoutStore;

/**
 * Reserved Page Builder editor shell (Phase 5).
 *
 * Served at /{admin}/page-builder/{ownerType}/{ownerId}/edit. This phase only
 * provides the shell: it resolves + guards the owner, reports the current
 * editor mode (stored in pb_layouts via LayoutStore — never in the content
 * record), draft/published status and the registered section/block counts, and
 * lets an editor switch between Classic and Page Builder mode. The actual visual
 * Vue editor and its assets are a later phase and are intentionally absent.
 *
 * Access requires the `page-builder.edit` permission; unsupported owner types
 * and missing owners are rejected with 404.
 */
class PageBuilderEditorPage extends Page
{
    protected string $view = 'page-builder::filament.pages.editor';

    protected static ?string $slug = 'page-builder-editor';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    // Deep-link only: never shown in the sidebar (it needs owner route params).
    protected static bool $shouldRegisterNavigation = false;

    public string $ownerType = '';

    public string $ownerId = '';

    public string $ownerLabel = '';

    public string $ownerTitle = '';

    public string $editorMode = PageBuilderLayout::MODE_CLASSIC;

    /** The requested editor locale (from `?locale=`), or null for the source locale. */
    public ?string $requestedLocale = null;

    /** The server-resolved locale actually opened (source locale for the default). */
    public string $resolvedLocale = '';

    /** The effective (locale-specific) layout owner type — `page`/`post` or `content_translation`. */
    public string $layoutOwnerType = '';

    /** The effective (locale-specific) layout owner id. */
    public string $layoutOwnerId = '';

    public bool $hasDraft = false;

    public bool $hasPublished = false;

    public int $sectionsCount = 0;

    public int $blocksCount = 0;

    /**
     * Owner-scoped route: /{admin}/page-builder/{ownerType}/{ownerId}/edit.
     */
    public static function getRoutePath(Panel $panel): string
    {
        return '/page-builder/{ownerType}/{ownerId}/edit';
    }

    /**
     * Route + navigation guard: editing requires the edit permission.
     */
    public static function canAccess(): bool
    {
        return function_exists('cms_can') ? (bool) cms_can('page-builder.edit') : true;
    }

    public function getTitle(): string
    {
        return __('Edit with Page Builder');
    }

    public function mount(string $ownerType, string $ownerId): void
    {
        // Defence in depth (canAccess already gates the route).
        abort_unless(static::canAccess(), 403);

        // Reject unknown owner types before touching the database.
        abort_unless(EditableOwners::isSupported($ownerType), 404);

        $owner = EditableOwners::resolve($ownerType, $ownerId);
        abort_if($owner === null, 404);

        $this->ownerType = $ownerType;
        $this->ownerId = (string) $owner->getKey();
        $this->ownerLabel = EditableOwners::label($ownerType);
        $this->ownerTitle = $owner->translatedTitle();

        // LT-M1B Increment 2 — locale-aware boot (same runtime as the Frontend
        // editor). The locale is context supplied as `?locale=`; the server (never
        // the client) re-resolves the effective owner from the canonical page +
        // locale through the ONE resolver and provisions the translated layout on
        // first open. An invalid / disabled / out-of-group locale fails closed here,
        // so the editor never silently opens the source layout.
        $this->requestedLocale = $this->localeFromRequest();
        $this->guardAndProvisionLocale($ownerType);

        $this->refreshState();
    }

    /**
     * The requested editor locale (context only) from the query string, or null
     * for the source locale. The backend re-resolves the effective owner from it.
     */
    private function localeFromRequest(): ?string
    {
        $locale = request()->query('locale');

        if (! is_string($locale)) {
            return null;
        }

        $locale = trim($locale);

        return $locale === '' ? null : $locale;
    }

    /**
     * Resolve the effective (locale-specific) layout owner from the canonical page
     * + requested locale and provision the translated layout on first open. Fails
     * closed on an invalid / disabled / out-of-group locale so the editor never
     * opens the source layout for a bad context.
     */
    private function guardAndProvisionLocale(string $ownerType): void
    {
        try {
            $effective = app(EffectiveEditorOwnerResolver::class)
                ->resolve($ownerType, $this->ownerId, $this->requestedLocale);

            app(FirstOpenLayoutProvisioner::class)->ensure($effective, $this->actorId());

            $this->resolvedLocale = $effective->locale;
            $this->layoutOwnerType = $effective->ownerType;
            $this->layoutOwnerId = (string) $effective->ownerId;
        } catch (EditorOwnerResolutionException $e) {
            abort($e->statusCode, $e->getMessage());
        }
    }

    private function actorId(): ?string
    {
        $id = auth()->id();

        return $id === null ? null : (string) $id;
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('usePageBuilder')
                ->label(__('Use Page Builder'))
                ->icon('heroicon-o-squares-2x2')
                ->visible(fn (): bool => $this->editorMode !== PageBuilderLayout::MODE_BUILDER)
                ->action(fn () => $this->setMode(PageBuilderLayout::MODE_BUILDER)),

            Action::make('useClassicEditor')
                ->label(__('Use Classic Editor'))
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->visible(fn (): bool => $this->editorMode !== PageBuilderLayout::MODE_CLASSIC)
                ->action(fn () => $this->setMode(PageBuilderLayout::MODE_CLASSIC)),
        ];
    }

    /**
     * Persist the chosen editor mode into pb_layouts and refresh the view state.
     */
    public function setMode(string $mode): void
    {
        abort_unless(static::canAccess(), 403);

        // Target the effective (locale-specific) owner so switching a translation's
        // mode never mutates the source locale's editor mode.
        app(LayoutStore::class)->setEditorMode($this->layoutOwnerType, $this->layoutOwnerId, $mode);

        $this->refreshState();

        Notification::make()
            ->title(__('Editor mode updated'))
            ->success()
            ->send();
    }

    /**
     * Re-read the owner's mode + draft/published status and the library counts.
     */
    private function refreshState(): void
    {
        $store = app(LayoutStore::class);
        $layout = $store->find($this->layoutOwnerType, $this->layoutOwnerId);

        $this->editorMode = $layout?->isPageBuilderMode()
            ? PageBuilderLayout::MODE_BUILDER
            : PageBuilderLayout::MODE_CLASSIC;

        $this->hasDraft = $layout?->draft_payload !== null;
        $this->hasPublished = (bool) $layout?->hasPublished();

        $status = PageBuilder::status();
        $this->sectionsCount = (int) $status['sections_count'];
        $this->blocksCount = (int) $status['blocks_count'];
    }
}
