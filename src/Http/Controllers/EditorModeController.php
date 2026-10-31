<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\PageBuilder\Authority\AuthorityResponse;
use Plugins\PageBuilder\Authority\ContentAuthority;
use Plugins\PageBuilder\Authority\PageBuilderAuthorityException;
use Plugins\PageBuilder\Editor\Translation\EditorOwnerResolutionException;
use Plugins\PageBuilder\Models\PageBuilderLayout;
use Plugins\PageBuilder\Support\EditableOwners;
use Plugins\PageBuilder\Support\EditorModeSwitcher;
use Plugins\PageBuilder\Support\LayoutVersionConflictException;
use Plugins\PageBuilder\Support\RenderSource;
use TheNguyen\CMS\ContentAuthority\ContentWriteException;

/**
 * `POST editor/context/{ownerType}/{ownerId}/mode` — switch which source renders
 * publicly (Classic content or the Page Builder layout) for the editor's effective
 * owner (`?locale=`), through {@see EditorModeSwitcher} (PB-CORE-AUTHORITY-REMEDIATION-R1).
 *
 * Body: `mode` (classic|builder), `publication_token` (from the editor context),
 * `content_revision`. 200 returns the new token; 403 authority; 409 stale token or
 * Core revision (zero writes); 422 invalid mode. Every response is private/no-store.
 */
final class EditorModeController
{
    public function __construct(
        private readonly EditorModeSwitcher $switcher,
        private readonly ContentAuthority $authority,
    ) {}

    public function update(Request $request, string $ownerType, int|string $ownerId): JsonResponse
    {
        abort_unless(function_exists('cms_can') && auth()->check() && cms_can('page-builder.edit'), 403);
        abort_unless(EditableOwners::isSupported($ownerType), 404);

        $owner = EditableOwners::resolve($ownerType, $ownerId);
        abort_if($owner === null, 404);

        $mode = $request->input('mode');
        if (! is_string($mode) || ! in_array($mode, [PageBuilderLayout::MODE_CLASSIC, PageBuilderLayout::MODE_BUILDER], true)) {
            return $this->json(['success' => false, 'error' => 'mode_invalid', 'message' => __('Choose Classic content or Page Builder content.')], 422);
        }

        $token = $request->input('publication_token');
        $revision = $request->input('content_revision');

        try {
            $layout = $this->switcher->switch(
                $owner,
                $this->localeFrom($request),
                RenderSource::fromStorage($mode),
                auth()->user(),
                is_numeric($revision) ? (int) $revision : null,
                is_string($token) ? $token : null,
            );
        } catch (PageBuilderAuthorityException|ContentWriteException $e) {
            /** @var JsonResponse */
            return AuthorityResponse::from($e, $this->authority->revisionOf($owner));
        } catch (LayoutVersionConflictException $e) {
            return $this->json([
                'success' => false,
                'error' => 'pb_mode_conflict',
                'message' => __('This page changed in another session. Reload it before switching the editor mode.'),
                'data' => $e->current,
            ], 409);
        } catch (EditorOwnerResolutionException $e) {
            return $this->json(['success' => false, 'error' => $e->reason, 'message' => $e->getMessage()], $e->statusCode);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'editor_mode' => $layout->editor_mode,
                'publication_token' => $layout->publicationToken(),
                'content_revision' => $this->authority->revisionOf($owner),
                'has_published' => $layout->hasPublished(),
            ],
        ], 200);
    }

    /** @param array<string, mixed> $body */
    private function json(array $body, int $status): JsonResponse
    {
        return response()->json($body, $status)->header('Cache-Control', 'no-store, private');
    }

    private function localeFrom(Request $request): ?string
    {
        $locale = $request->query('locale', $request->input('locale'));

        return is_string($locale) && trim($locale) !== '' ? trim($locale) : null;
    }
}
