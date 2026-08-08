<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Plugins\PageBuilder\Library\Availability\ResourceAvailabilityResolver;
use Plugins\PageBuilder\PageTemplate\TemplateApplicationService;
use Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface;
use Plugins\PageBuilder\Support\EditableOwners;
use TheNguyen\CMS\Models\Content;

/**
 * Page Template application endpoints (Phase 26.47D, Part A — Design Library
 * "Use This Template" destination workflow).
 *
 *   GET  /page-builder/library/template.js               — the destination workflow module
 *   GET  /page-builder/library/template/destinations     — search existing pages/posts
 *   POST /page-builder/library/template/create-apply     — create a Draft page/post + apply
 *   POST /page-builder/library/template/apply-existing   — apply to an existing page/post draft
 *
 * The RID is always resolved server-side through the repository; the client never
 * sends a Builder payload. Application affects the Builder draft only and always
 * goes through the (unchanged) FullPageImportService via TemplateApplicationService.
 */
final class PageTemplateController
{
    /** Create a new Draft page/post and apply the selected template to its draft. */
    public function createApply(Request $request, TemplateApplicationService $service): JsonResponse
    {
        $ownerType = (string) $request->input('ownerType', '');
        abort_unless($this->canContent($ownerType, 'create'), 403);
        $this->ensureImportable($request);

        $outcome = $service->createAndApply(
            rid: (string) $request->input('rid', ''),
            ownerType: $ownerType,
            title: (string) $request->input('title', ''),
            locale: $this->stringOrNull($request->input('locale')),
            actorId: $this->actorId($request),
        );

        return response()->json(
            ['success' => $outcome->ok, 'data' => $outcome->toArray()],
            $outcome->ok ? 200 : 422,
        );
    }

    /** Apply the selected template to an existing page/post's Builder draft. */
    public function applyExisting(Request $request, TemplateApplicationService $service): JsonResponse
    {
        $ownerType = (string) $request->input('ownerType', '');
        abort_unless($this->canContent($ownerType, 'edit'), 403);
        $this->ensureImportable($request);

        $outcome = $service->applyToExisting(
            rid: (string) $request->input('rid', ''),
            ownerType: $ownerType,
            ownerId: $request->input('ownerId', ''),
            planHash: $this->stringOrNull($request->input('planHash')),
            revision: $this->stringOrNull($request->input('revision')),
            actorId: $this->actorId($request),
        );

        return response()->json(
            ['success' => $outcome->ok, 'data' => $outcome->toArray()],
            $outcome->ok ? 200 : 422,
        );
    }

    /**
     * Search existing pages/posts of a single owner type for the "Use Existing"
     * destination picker. Scoped strictly to the selected owner type.
     */
    public function destinations(Request $request): JsonResponse
    {
        $ownerType = (string) $request->query('ownerType', '');
        abort_unless($this->canContent($ownerType, 'edit'), 403);
        abort_unless(EditableOwners::isSupported($ownerType), 422, 'Unsupported destination type.');

        $q = trim((string) $request->query('q', ''));

        $query = Content::query()->where('type', $ownerType)->with('translations')->latest('id')->limit(20);
        if ($q !== '') {
            $query->where(function ($outer) use ($q): void {
                $outer->whereHas('translations', fn ($t) => $t->where('title', 'like', '%'.$q.'%'))
                    ->orWhere('id', $q);
            });
        }

        $items = $query->get()->map(static function (Content $content): array {
            $title = optional($content->translations->first())->title;

            return [
                'id' => $content->getKey(),
                'title' => is_string($title) && $title !== '' ? $title : '#'.$content->getKey(),
                'status' => $content->status,
            ];
        })->all();

        return response()->json(['success' => true, 'data' => $items]);
    }

    /**
     * Serves the destination workflow ES module (no build step; the source file IS
     * the served asset — mirrors the Design Library + package scripts).
     */
    public function script(): Response
    {
        $path = dirname(__DIR__, 3).'/resources/js/design-library/page-template.js';
        $js = is_file($path) ? (string) file_get_contents($path) : '';

        return response($js, 200, [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /**
     * Base editor permission + the destination content-type permission
     * (pages.create / posts.edit, …). Both are required.
     */
    private function canContent(string $ownerType, string $ability): bool
    {
        if (! function_exists('cms_can') || ! cms_can('page-builder.edit')) {
            return false;
        }

        if (! EditableOwners::isSupported($ownerType)) {
            return false;
        }

        return (bool) cms_can($ownerType.'s.'.$ability);
    }

    /**
     * Entitlement gate (Phase 26.45). A locked template is visible and previewable
     * but must never be applied — refuse before the request reaches the service.
     * The RID is resolved through the repository, the single source of truth.
     */
    private function ensureImportable(Request $request): void
    {
        $rid = $request->input('rid');
        if (! is_string($rid) || $rid === '') {
            return; // the service reports the missing/invalid RID.
        }

        $resource = app(DesignResourceRepositoryInterface::class)->tryFind($rid);
        if ($resource === null) {
            return; // the service reports resource_not_found.
        }

        abort_if(
            ! app(ResourceAvailabilityResolver::class)->isImportable($resource),
            403,
            'This template requires an upgrade before it can be used.',
        );
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function actorId(Request $request): ?string
    {
        $user = $request->user();

        return $user !== null ? (string) $user->getAuthIdentifier() : null;
    }
}
