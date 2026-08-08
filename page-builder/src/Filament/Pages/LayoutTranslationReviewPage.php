<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Str;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\TranslationGroupKey;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationDecision;
use Plugins\PageBuilder\LayoutTranslation\Review\Session\ReviewSessionId;
use Plugins\PageBuilder\LayoutTranslation\Review\Session\StaleReviewSessionException;
use Plugins\PageBuilder\LayoutTranslation\ReviewUi\ReviewWorkspaceInteractor;
use Plugins\PageBuilder\LayoutTranslation\ReviewUi\ReviewWorkspaceView;
use Throwable;

/**
 * Layout Translation Review Workspace — Filament entry point + Livewire shell (LT-8C).
 *
 * Served at /{admin}/page-builder/translation-review/{ownerType}/{ownerId}/{locale}. This class is the UI
 * SHELL only: it holds nothing but EPHEMERAL VISUAL STATE (selected node, expanded groups, active panel,
 * filter) and forwards EVERY domain action to the frozen Workspace Runtime through
 * {@see ReviewWorkspaceInteractor} — one UI action, one runtime op (INV-LT-8-PRES-01/02). It never holds
 * the DecisionSet, provenance, acceptance, or execution state; it never assembles a command, constructs
 * acceptance, or calls the coordinator. Preview is obtained solely through the runtime's preview binding
 * and renders fail-soft when unavailable.
 *
 * Deep-link, permission- and flag-gated. If the runtime ports are not yet wired (LT-8D), the page degrades
 * to a fail-soft blocked state rather than erroring.
 */
class LayoutTranslationReviewPage extends Page
{
    protected string $view = 'page-builder::filament.pages.layout-translation-review';

    protected static ?string $slug = 'page-builder-translation-review';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-language';

    // Deep-link only: it needs owner + locale route params.
    protected static bool $shouldRegisterNavigation = false;

    // -- Ephemeral visual state (INV-LT-8-PRES-01: nothing here is domain truth) ------------------------

    public string $ownerType = '';

    public string $ownerId = '';

    public string $locale = '';

    public string $sessionId = '';

    public ?string $selectedOrigin = null;

    /** @var array<string, bool> */
    public array $expandedGroups = [];

    public string $activePanel = 'preview';

    public string $filter = '';

    public bool $blocked = false;

    public string $blockReason = '';

    public bool $stale = false;

    /** @var array<string, mixed>|null ephemeral typed-result view model after submit */
    public ?array $result = null;

    public static function getRoutePath(Panel $panel): string
    {
        return '/page-builder/translation-review/{ownerType}/{ownerId}/{locale}';
    }

    public static function canAccess(): bool
    {
        if (! (bool) config('page-builder.translation.enabled', false)) {
            return false;
        }

        return function_exists('cms_can') ? (bool) cms_can('page-builder.edit') : true;
    }

    public function getTitle(): string
    {
        return __('Review translation sync');
    }

    public function mount(string $ownerType, string $ownerId, string $locale): void
    {
        abort_unless(static::canAccess(), 403);

        $this->ownerType = $ownerType;
        $this->ownerId = $ownerId;
        $this->locale = $locale;
        $this->sessionId = (string) Str::uuid();

        try {
            $session = $this->interactor()->open(ReviewSessionId::of($this->sessionId), $this->target());
            $this->selectedOrigin = $session->selected();
        } catch (Throwable $e) {
            $this->failSoft($e);
        }
    }

    // -- Domain actions: each maps 1:1 to a single runtime operation -----------------------------------

    public function selectNode(string $origin): void
    {
        $this->runtime(fn () => $this->interactor()->select($this->sid(), $this->target(), $origin));
        $this->selectedOrigin = $origin;
    }

    public function decide(string $origin, string $decision): void
    {
        $choice = SynchronizationDecision::tryFrom($decision);
        if ($choice === null) {
            return;
        }
        $this->runtime(fn () => $this->interactor()->decide($this->sid(), $this->target(), $origin, $choice));
    }

    public function resetDecision(string $origin): void
    {
        $this->runtime(fn () => $this->interactor()->resetDecision($this->sid(), $this->target(), $origin));
    }

    public function goToNextUnreviewed(): void
    {
        $session = $this->runtime(fn () => $this->interactor()->selectNextUnreviewed($this->sid(), $this->target()));
        if ($session !== null) {
            $this->selectedOrigin = $session->selected();
        }
    }

    public function submitReview(): void
    {
        try {
            $result = $this->interactor()->submit($this->sid(), $this->target(), $this->actor());
        } catch (StaleReviewSessionException) {
            $this->stale = true;

            return;
        } catch (Throwable $e) {
            $this->failSoft($e);

            return;
        }

        $this->result = app(ReviewWorkspaceView::class)->result($result);

        $notification = Notification::make()->title($this->outcomeTitle($result->outcome->value));
        $result->isSuccessful() ? $notification->success() : $notification->danger();
        $notification->send();
    }

    // -- Ephemeral visual actions (no runtime call) ----------------------------------------------------

    public function toggleGroup(string $classification): void
    {
        $this->expandedGroups[$classification] = ! ($this->expandedGroups[$classification] ?? true);
    }

    public function setActivePanel(string $panel): void
    {
        $this->activePanel = $panel;
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    // -- Render data (built once per render; single provider re-derivation) -----------------------------

    /**
     * @return array<string, mixed>
     */
    public function viewData(): array
    {
        if ($this->blocked || $this->result !== null) {
            return ['blocked' => $this->blocked, 'block_reason' => $this->blockReason, 'stale' => $this->stale, 'result' => $this->result, 'workspace' => null, 'preview' => null];
        }

        try {
            $session = $this->interactor()->session($this->sid(), $this->target());
        } catch (StaleReviewSessionException) {
            return ['blocked' => false, 'block_reason' => '', 'stale' => true, 'result' => null, 'workspace' => null, 'preview' => null];
        } catch (Throwable $e) {
            $this->failSoft($e);

            return ['blocked' => true, 'block_reason' => $this->blockReason, 'stale' => false, 'result' => null, 'workspace' => null, 'preview' => null];
        }

        $view = app(ReviewWorkspaceView::class);
        $preview = $this->selectedOrigin !== null ? $view->preview($session, $this->selectedOrigin) : null;

        return [
            'blocked' => false,
            'block_reason' => '',
            'stale' => false,
            'result' => null,
            'workspace' => $view->workspace($session),
            'preview' => $preview,
        ];
    }

    // -- Internals -------------------------------------------------------------------------------------

    private function interactor(): ReviewWorkspaceInteractor
    {
        return app(ReviewWorkspaceInteractor::class);
    }

    private function target(): PageTranslationKey
    {
        return PageTranslationKey::forGroup(
            TranslationGroupKey::forOwner($this->ownerType, $this->ownerId),
            $this->locale,
        );
    }

    private function sid(): ReviewSessionId
    {
        return ReviewSessionId::of($this->sessionId);
    }

    private function actor(): ?string
    {
        $user = function_exists('auth') ? auth()->user() : null;

        return $user !== null ? (string) $user->getAuthIdentifier() : null;
    }

    /**
     * Run one runtime operation, degrading a stale plan to the stale banner and any other failure to the
     * fail-soft blocked state.
     *
     * @template T
     * @param  callable():T  $op
     * @return T|null
     */
    private function runtime(callable $op): mixed
    {
        try {
            return $op();
        } catch (StaleReviewSessionException) {
            $this->stale = true;
        } catch (Throwable $e) {
            $this->failSoft($e);
        }

        return null;
    }

    private function failSoft(Throwable $e): void
    {
        $this->blocked = true;
        $this->blockReason = __('Layout translation review is not available for this target.');
    }

    private function outcomeTitle(string $outcome): string
    {
        return match ($outcome) {
            'executed' => __('Synchronization applied.'),
            'no_op' => __('Nothing to apply.'),
            'plan_stale', 'concurrent_conflict' => __('The plan is out of date — please re-review.'),
            default => __('Synchronization was not applied.'),
        };
    }
}
