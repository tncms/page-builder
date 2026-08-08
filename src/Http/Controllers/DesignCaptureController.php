<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\PageBuilder\Capture\DesignCapturePipeline;
use Plugins\PageBuilder\Capture\DesignCaptureResult;

/**
 * Save as Design endpoint (Phase 26.48).
 *
 *   POST /api/page-builder/builder/capture
 *
 * Runs the Design Capture Pipeline against a Builder selection. This is an
 * external persistence action, NOT a Builder command: it never touches
 * BuilderState, the CommandBus, or undo history (Rules 13/20). The author is the
 * current authenticated user (server-side); no internal id is returned to the
 * Builder (Rule 17).
 */
final class DesignCaptureController
{
    public function store(Request $request, DesignCapturePipeline $pipeline): JsonResponse
    {
        abort_unless(function_exists('cms_can') && cms_can('page-builder.edit'), 403);

        $selection = [
            'node' => is_array($request->input('node')) ? $request->input('node') : [],
            'kind' => (string) $request->input('kind', ''),
        ];

        $metadata = [
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'category' => $request->input('category'),
            'tags' => $request->input('tags', []),
        ];

        $result = $pipeline->capture(
            $selection,
            $metadata,
            $this->author($request),
            $request->boolean('mode') === true || $request->input('mode') === 'copy',
        );

        $status = match ($result->status) {
            DesignCaptureResult::SAVED => 200,
            DesignCaptureResult::DUPLICATE => 200,
            default => 422,
        };

        return response()->json(['success' => $result->ok(), 'data' => $result->toArray()], $status);
    }

    /**
     * A display author for the record; never exposed to the Builder (Rule 17).
     */
    private function author(Request $request): ?string
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        return is_string($user->name ?? null) ? $user->name : (string) $user->getAuthIdentifier();
    }
}
