<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Plugins\PageBuilder\Management\DesignResourceManager;
use Plugins\PageBuilder\Management\Exceptions\DesignResourceNotFoundException;
use Plugins\PageBuilder\Management\Exceptions\MetadataValidationException;
use Plugins\PageBuilder\Management\Exceptions\UnauthorizedDesignActionException;
use Plugins\PageBuilder\Management\MetadataUpdater;

/**
 * The "My Designs" management surface inside the Design Library (Phase 26.49).
 *
 * Serves the management page and its JSON action endpoints (metadata edit,
 * archive, restore, soft-delete, duplicate, open-in-builder). It is a thin
 * transport layer: authorization (owner OR admin), immutability and lifecycle
 * rules all live in {@see DesignResourceManager}. The controller enforces only
 * the coarse `page-builder.edit` gate; per-resource ownership is verified inside
 * the manager and surfaced here as a 403.
 */
final class DesignManagementController
{
    public function index(Request $request, DesignResourceManager $manager): Response
    {
        abort_unless($this->authorized(), 403);

        $viewModel = $manager->list([
            'kind' => $request->query('kind'),
            'category' => $request->query('category'),
            'visibility' => $request->query('visibility'),
            'version' => $request->query('version'),
            'tag' => $request->query('tag'),
            'search' => $request->query('search'),
            'archived' => $request->boolean('archived'),
            'sort' => $request->query('sort'),
        ], $request->user());

        $viewModel['scriptUrl'] = $this->routeOrPath('page-builder.library.my-designs.script', 'page-builder/library/my-designs/manage.js');
        $viewModel['csrfToken'] = csrf_token();
        $viewModel['libraryUrl'] = $this->routeOrPath('page-builder.library', 'page-builder/library');
        $viewModel['endpoints'] = [
            'metadata' => $this->routeOrPath('page-builder.library.my-designs.metadata', 'page-builder/library/my-designs/metadata'),
            'archive' => $this->routeOrPath('page-builder.library.my-designs.archive', 'page-builder/library/my-designs/archive'),
            'restore' => $this->routeOrPath('page-builder.library.my-designs.restore', 'page-builder/library/my-designs/restore'),
            'delete' => $this->routeOrPath('page-builder.library.my-designs.delete', 'page-builder/library/my-designs/delete'),
            'duplicate' => $this->routeOrPath('page-builder.library.my-designs.duplicate', 'page-builder/library/my-designs/duplicate'),
            'open' => $this->routeOrPath('page-builder.library.my-designs.open', 'page-builder/library/my-designs/open'),
        ];
        $viewModel['visibilities'] = MetadataUpdater::VISIBILITIES;

        return response()->view('page-builder::design-library.my-designs', $viewModel);
    }

    public function metadata(Request $request, DesignResourceManager $manager): JsonResponse
    {
        return $this->guard(function () use ($request, $manager): JsonResponse {
            $rid = (string) $request->input('rid', '');

            $input = [];
            foreach (MetadataUpdater::EDITABLE as $field) {
                if ($request->has($field)) {
                    $input[$field] = $field === 'tags' ? $this->tags($request->input('tags')) : $request->input($field);
                }
            }

            $record = $manager->updateMetadata($rid, $input, $request->user());

            return response()->json(['success' => true, 'data' => [
                'rid' => $record['rid'] ?? $rid,
                'name' => $record['name'] ?? '',
                'category' => $record['category'] ?? 'general',
                'tags' => $record['tags'] ?? [],
                'visibility' => $record['visibility'] ?? 'private',
                'description' => $record['description'] ?? null,
                'versionNotes' => $record['versionNotes'] ?? null,
            ]]);
        });
    }

    public function archive(Request $request, DesignResourceManager $manager): JsonResponse
    {
        return $this->guard(function () use ($request, $manager): JsonResponse {
            $manager->archive((string) $request->input('rid', ''), $request->user());

            return response()->json(['success' => true, 'data' => ['archived' => true]]);
        });
    }

    public function restore(Request $request, DesignResourceManager $manager): JsonResponse
    {
        return $this->guard(function () use ($request, $manager): JsonResponse {
            $manager->restore((string) $request->input('rid', ''), $request->user());

            return response()->json(['success' => true, 'data' => ['archived' => false]]);
        });
    }

    public function delete(Request $request, DesignResourceManager $manager): JsonResponse
    {
        return $this->guard(function () use ($request, $manager): JsonResponse {
            $manager->delete((string) $request->input('rid', ''), $request->user());

            return response()->json(['success' => true, 'data' => ['deleted' => true]]);
        });
    }

    public function duplicate(Request $request, DesignResourceManager $manager): JsonResponse
    {
        return $this->guard(function () use ($request, $manager): JsonResponse {
            $rid = $manager->duplicate((string) $request->input('rid', ''), $request->user());

            return response()->json(['success' => true, 'data' => ['rid' => $rid]]);
        });
    }

    public function open(Request $request, DesignResourceManager $manager): JsonResponse
    {
        return $this->guard(function () use ($request, $manager): JsonResponse {
            $workingCopy = $manager->openInBuilder((string) $request->input('rid', ''), $request->user());

            return response()->json(['success' => true, 'data' => $workingCopy]);
        });
    }

    /**
     * Serve the management ES module (source file IS the served asset — no build).
     */
    public function script(): Response
    {
        abort_unless($this->authorized(), 403);

        $path = dirname(__DIR__, 3).'/resources/js/design-library/my-designs.js';
        $js = is_file($path) ? (string) file_get_contents($path) : '';

        return response($js, 200, [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /**
     * Run an action, mapping the manager's exceptions to HTTP status codes.
     *
     * @param  callable(): JsonResponse  $action
     */
    private function guard(callable $action): JsonResponse
    {
        abort_unless($this->authorized(), 403);

        try {
            return $action();
        } catch (MetadataValidationException $e) {
            return response()->json(['success' => false, 'issues' => $e->issues], 422);
        } catch (UnauthorizedDesignActionException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (DesignResourceNotFoundException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    private function authorized(): bool
    {
        return function_exists('cms_can') && cms_can('page-builder.edit');
    }

    /**
     * @return array<int, string>
     */
    private function tags(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_string'));
        }
        if (is_string($value)) {
            return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $t): bool => $t !== ''));
        }

        return [];
    }

    private function routeOrPath(string $name, string $path): string
    {
        try {
            return route($name);
        } catch (\Throwable) {
            return url($path);
        }
    }
}
