<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\PageBuilder\Library\Availability\ResourceAvailabilityResolver;
use Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface;
use Plugins\PageBuilder\Repository\Rid;

/**
 * Resolves a Builder Resource's id-free payload for native insertion (Phase 26.47E).
 *
 *   GET /api/page-builder/builder/resources?rid=<rid>
 *
 * The editor palette carries only light metadata (Phase 26.47E, Rule 10); when a
 * user inserts a resource the editor fetches its id-free tree here and dispatches
 * it through the EXISTING insert-pattern / insert-component commands — this
 * endpoint never mutates state and never touches the CommandBus. The RID is
 * resolved server-side through the authoritative repository; availability
 * (Phase 26.45) is enforced so a locked resource can never be inserted.
 */
final class BuilderResourceController
{
    /** Palette-insertable kinds only (kit is reserved, Rule 4). */
    private const PALETTE_KINDS = [Rid::KIND_SECTION, Rid::KIND_BLOCK];

    public function payload(
        Request $request,
        DesignResourceRepositoryInterface $repository,
        ResourceAvailabilityResolver $availability,
    ): JsonResponse {
        abort_unless(function_exists('cms_can') && cms_can('page-builder.edit'), 403);

        $rid = (string) $request->query('rid', '');
        $resource = $rid !== '' ? $repository->tryFind($rid) : null;
        abort_if($resource === null, 404, 'Resource not found.');

        abort_unless(in_array($resource->kind, self::PALETTE_KINDS, true), 422, 'This resource cannot be inserted.');

        abort_unless(
            $availability->isImportable($resource),
            403,
            'This design requires an upgrade before it can be inserted.',
        );

        $sections = is_array($resource->payload['sections'] ?? null) ? $resource->payload['sections'] : [];

        return response()->json([
            'success' => true,
            'data' => [
                'rid' => $resource->rid,
                'kind' => $resource->kind,
                // The full id-free section tree — inserted via insert-pattern.
                'sections' => $sections,
                // For a block resource, the bare block node — inserted via
                // insert-component into the current container when one is selected.
                'block' => $resource->kind === Rid::KIND_BLOCK ? $this->firstBlock($sections) : null,
            ],
        ]);
    }

    /**
     * The first block node inside the first section of a (block) resource payload,
     * or null when the shape is unexpected.
     *
     * @param  array<int, mixed>  $sections
     * @return array<string, mixed>|null
     */
    private function firstBlock(array $sections): ?array
    {
        $section = $sections[0] ?? null;
        if (! is_array($section)) {
            return null;
        }

        $block = ($section['blocks'][0] ?? null);

        return is_array($block) ? $block : null;
    }
}
