<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Plugins\PageBuilder\Support\PageBuilderManager;

/**
 * Read-only editor bootstrap endpoint (Phase 6).
 *
 * Returns the JSON the future Vue editor needs to render its palette + inspector:
 * every section/block definition (with its field schema) plus the design tokens
 * and breakpoints. It ships NO markup or scripts — just data. Gated by the
 * `page-builder.edit` permission, consistent with the reserved editor page.
 */
final class EditorMetadataController
{
    public function __invoke(): JsonResponse
    {
        abort_unless(function_exists('cms_can') && cms_can('page-builder.edit'), 403);

        /** @var PageBuilderManager $manager */
        $manager = app('page-builder');

        return response()->json([
            'success' => true,
            'data' => $manager->editorMetadata(),
        ]);
    }
}
