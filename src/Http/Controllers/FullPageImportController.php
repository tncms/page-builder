<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\PageBuilder\Import\FullPageImportRequest;
use Plugins\PageBuilder\Import\FullPageImportService;
use Plugins\PageBuilder\Library\Availability\ResourceAvailabilityResolver;
use Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface;

/**
 * Endpoints for the Full Page Template import workflow.
 *
 *   POST /page-builder/library/import/plan  — dry run (read-only), returns a plan
 *   POST /page-builder/library/import        — atomic commit, returns a result
 *
 * The RID is resolved server-side through the repository; the client never sends
 * a builder payload. Import affects the Builder draft only.
 */
final class FullPageImportController
{
    public function plan(Request $request, FullPageImportService $service): JsonResponse
    {
        abort_unless($this->authorized(), 403);
        $this->ensureImportable($request);

        $plan = $service->plan(FullPageImportRequest::fromArray($request->all(), $this->actorId($request)));

        return response()->json(['success' => true, 'data' => $plan->toArray()]);
    }

    public function commit(Request $request, FullPageImportService $service): JsonResponse
    {
        abort_unless($this->authorized(), 403);
        $this->ensureImportable($request);

        $result = $service->import(FullPageImportRequest::fromArray($request->all(), $this->actorId($request)));

        return response()->json(
            ['success' => $result->ok, 'data' => $result->toArray()],
            $result->ok ? 200 : 422,
        );
    }

    private function authorized(): bool
    {
        return function_exists('cms_can') && cms_can('page-builder.edit');
    }

    /**
     * Entitlement gate (Phase 26.45). A locked resource is visible and previewable
     * but must never be importable — the import workflow itself is unchanged; this
     * only refuses the request before it reaches the (frozen) service. The RID is
     * always resolved through the repository, the single source of truth.
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
            'This design requires an upgrade before it can be imported.',
        );
    }

    private function actorId(Request $request): ?string
    {
        $user = $request->user();

        return $user !== null ? (string) $user->getAuthIdentifier() : null;
    }
}
