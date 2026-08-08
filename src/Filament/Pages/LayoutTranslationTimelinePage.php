<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Panel;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\TranslationGroupKey;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntryId;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Projection\TimelineFilter;
use Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure\TimelineApplicationService;
use Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure\TimelineQueryService;
use Throwable;

/**
 * Synchronization Timeline — read-only Filament presentation (LT-9C).
 *
 * Served at /{admin}/page-builder/translation-timeline/{ownerType}/{ownerId}/{locale}. It is a READ-ONLY
 * shell over the frozen Timeline Runtime: it holds only ephemeral view state (filter, sort, page, selected
 * entry, active panel) and obtains EVERY timeline, list, replay report, and explanation through the
 * application/query services — never by querying persistence or running runtime logic itself
 * (INV-LT-9C-01/03). It exposes no re-execution, edit, or delete: the timeline is read-only. Replay and
 * explainability are gated on their own permissions and are observational only.
 */
class LayoutTranslationTimelinePage extends Page
{
    protected string $view = 'page-builder::filament.pages.layout-translation-timeline';

    protected static ?string $slug = 'page-builder-translation-timeline';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static bool $shouldRegisterNavigation = false;

    // -- Ephemeral view state (no domain state) --------------------------------------------------------

    public string $ownerType = '';

    public string $ownerId = '';

    public string $locale = '';

    public string $filterActor = '';

    public bool $gapsOnly = false;

    public string $sort = 'executed_at';

    public string $direction = 'desc';

    public int $page = 1;

    public int $perPage = 20;

    public ?int $selectedEntryId = null;

    public string $activePanel = 'detail';

    public bool $blocked = false;

    /** @var array<string, mixed>|null ephemeral replay report */
    public ?array $replay = null;

    /** @var array<string, mixed>|null ephemeral explainability report */
    public ?array $explain = null;

    public static function getRoutePath(Panel $panel): string
    {
        return '/page-builder/translation-timeline/{ownerType}/{ownerId}/{locale}';
    }

    public static function canAccess(): bool
    {
        if (! (bool) config('page-builder.translation.enabled', false)) {
            return false;
        }

        return function_exists('cms_can') ? (bool) cms_can('timeline.view') : true;
    }

    public function getTitle(): string
    {
        return __('Synchronization timeline');
    }

    public function mount(string $ownerType, string $ownerId, string $locale): void
    {
        abort_unless(static::canAccess(), 403);

        $this->ownerType = $ownerType;
        $this->ownerId = $ownerId;
        $this->locale = $locale;
    }

    // -- Ephemeral view actions ------------------------------------------------------------------------

    public function selectEntry(int $entryId): void
    {
        $this->selectedEntryId = $entryId;
        $this->replay = null;
        $this->explain = null;
        $this->activePanel = 'detail';
    }

    public function setSort(string $sort): void
    {
        $this->sort = $sort;
    }

    public function setDirection(string $direction): void
    {
        $this->direction = $direction === 'asc' ? 'asc' : 'desc';
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function toggleGapsOnly(): void
    {
        $this->gapsOnly = ! $this->gapsOnly;
        $this->page = 1;
    }

    public function setActivePanel(string $panel): void
    {
        $this->activePanel = $panel;
    }

    // -- Observational actions (permission-gated; delegated to the runtime) ----------------------------

    public function requestReplay(int $entryId): void
    {
        abort_unless($this->can('timeline.replay'), 403);

        $this->replay = $this->app()->replay($this->target(), TimelineEntryId::of($entryId))->toArray();
        $this->activePanel = 'replay';
    }

    public function requestExplain(int $entryId): void
    {
        abort_unless($this->can('timeline.explain'), 403);

        $this->explain = $this->app()->explain($this->target(), TimelineEntryId::of($entryId))?->toArray();
        $this->activePanel = 'explain';
    }

    // -- Render data (delegated entirely to the services) ----------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function viewData(): array
    {
        try {
            $list = $this->query()->list(
                $this->target(),
                TimelineFilter::any()->withActor($this->filterActor !== '' ? $this->filterActor : null),
                $this->gapsOnly,
                $this->sort,
                $this->direction,
                $this->page,
                $this->perPage,
            );

            $timeline = $this->app()->timeline($this->target());
            $selected = $this->selectedEntryId !== null
                ? $timeline->entryFor(TimelineEntryId::of($this->selectedEntryId))?->toArray()
                : null;

            return [
                'blocked' => false,
                'list' => $list->toArray(),
                'summary' => $timeline->summary()->toArray(),
                'statistics' => $this->app()->statistics($this->target())->toArray(),
                'selected' => $selected,
                'replay' => $this->replay,
                'explain' => $this->explain,
                'can_replay' => $this->can('timeline.replay'),
                'can_explain' => $this->can('timeline.explain'),
            ];
        } catch (Throwable) {
            $this->blocked = true;

            return ['blocked' => true, 'list' => null, 'summary' => null, 'statistics' => null,
                'selected' => null, 'replay' => null, 'explain' => null, 'can_replay' => false, 'can_explain' => false];
        }
    }

    // -- Internals -------------------------------------------------------------------------------------

    private function app(): TimelineApplicationService
    {
        return app(TimelineApplicationService::class);
    }

    private function query(): TimelineQueryService
    {
        return app(TimelineQueryService::class);
    }

    private function target(): PageTranslationKey
    {
        return PageTranslationKey::forGroup(
            TranslationGroupKey::forOwner($this->ownerType, $this->ownerId),
            $this->locale,
        );
    }

    private function can(string $permission): bool
    {
        return function_exists('cms_can') ? (bool) cms_can($permission) : true;
    }
}
