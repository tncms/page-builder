<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Filament\Concerns;

use Filament\Notifications\Notification;
use Livewire\Attributes\Locked;
use Plugins\PageBuilder\Authority\AuthorityResponse;
use Plugins\PageBuilder\Authority\ContentAuthority;
use Plugins\PageBuilder\Authority\PageBuilderAuthorityException;
use Plugins\PageBuilder\Models\PageBuilderLayout;
use Plugins\PageBuilder\Support\EditableOwners;
use Plugins\PageBuilder\Support\EditorModeSwitcher;
use Plugins\PageBuilder\Support\LayoutStore;
use Plugins\PageBuilder\Support\LayoutVersionConflictException;
use Plugins\PageBuilder\Support\RenderSource;
use TheNguyen\CMS\ContentAuthority\ContentRevisionConflict;
use TheNguyen\CMS\ContentAuthority\ContentWriteException;
use TheNguyen\CMS\Models\Content;

/**
 * Editor-page authority for the two Page Builder editor shells (PB-CORE37-R1).
 *
 * - Opening the editor needs draft authority on the canonical content
 *   ({@see ContentAuthority::assertCanEdit()}); extension-managed content is refused.
 * - The render-mode switch is a public Livewire method, so it re-derives everything
 *   server-side from LOCKED properties (the owner ids cannot be swapped by a forged
 *   request). A switch on a layout that has a published payload changes what renders
 *   publicly, so it needs publication authority; otherwise draft authority.
 * - The switch runs as a Core content unit against the Core revision captured at
 *   mount, then a row-locked CAS on the layout's publication token captured at
 *   mount. Either stale is refused with zero writes and an actionable notice.
 *
 * The using page declares `ownerType`, `ownerId`, `layoutOwnerType`,
 * `layoutOwnerId` and `editorMode` (owner ids marked #[Locked]).
 */
trait AuthorizesLayoutOwner
{
    /** The Core revision this editor session was opened against (PB-CORE37-R1). */
    #[Locked]
    public ?int $contentRevision = null;

    /** The layout's public-state token when this session last read it. */
    #[Locked]
    public string $publicationToken = '';

    /** Abort unless the actor holds draft authority on the canonical owner. */
    protected function authorizeEditorAccess(Content $owner): void
    {
        try {
            app(ContentAuthority::class)->assertCanEdit($owner, auth()->user());
        } catch (PageBuilderAuthorityException $e) {
            abort($e->statusCode, AuthorityResponse::message($e));
        }

        $this->contentRevision = app(ContentAuthority::class)->revisionOf($owner);
    }

    /** Capture the layout's current public-state token (after mount / a switch). */
    protected function capturePublicationToken(): void
    {
        $this->publicationToken = app(LayoutStore::class)->currentPublicationToken($this->layoutOwnerType, $this->layoutOwnerId);
    }

    /**
     * Authorize and persist a render-mode switch. Returns true when it was written.
     */
    protected function switchEditorMode(string $mode): bool
    {
        if (! in_array($mode, [PageBuilderLayout::MODE_CLASSIC, PageBuilderLayout::MODE_BUILDER], true)) {
            abort(422);
        }

        $owner = EditableOwners::resolve($this->ownerType, $this->ownerId);
        abort_if($owner === null, 404);

        try {
            app(EditorModeSwitcher::class)->switchOwner(
                $owner,
                $this->layoutOwnerType,
                $this->layoutOwnerId,
                RenderSource::fromStorage($mode),
                auth()->user(),
                $this->contentRevision,
                $this->publicationToken,
            );
        } catch (ContentRevisionConflict|LayoutVersionConflictException) {
            $this->notifyRefusal(__('This page changed in another session. Reload it before switching the editor mode.'));

            return false;
        } catch (PageBuilderAuthorityException|ContentWriteException $e) {
            $this->notifyRefusal(AuthorityResponse::message($e));

            return false;
        }

        $this->capturePublicationToken();

        return true;
    }

    private function notifyRefusal(string $message): void
    {
        Notification::make()
            ->title(__('Editor mode not changed'))
            ->body($message)
            ->danger()
            ->persistent()
            ->send();
    }
}
