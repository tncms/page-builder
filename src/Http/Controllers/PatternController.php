<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Plugins\PageBuilder\Patterns\Dependencies\PatternDependencyResolver;
use Plugins\PageBuilder\Patterns\Pattern;
use Plugins\PageBuilder\Patterns\PatternManager;
use Plugins\PageBuilder\Patterns\PatternVariableResolver;
use Plugins\PageBuilder\Patterns\Packs\PatternPack;
use Plugins\PageBuilder\Support\CatalogLocale;
use Plugins\PageBuilder\Patterns\Preview\PatternPreviewResolver;

/**
 * Pattern Library endpoints (Phase 25). `index` lists lightweight manifests +
 * categories (no payloads — §20 performance); `show` returns a single pattern's
 * manifest + its (lazy) Builder payload for preview / insert; `resolve` (Phase 25.1)
 * applies the pattern's variables to its payload template and returns a plain
 * Builder payload ready to insert; `thumbnail` streams a pattern's preview image.
 * Gated by `page-builder.edit`.
 *
 * The variable / preview resolvers are optional constructor deps resolved lazily
 * from the container so existing call sites that build the controller with only a
 * {@see PatternManager} keep working.
 */
final class PatternController
{
    public function __construct(
        private readonly PatternManager $manager,
        private readonly ?PatternVariableResolver $variables = null,
        private readonly ?PatternPreviewResolver $previews = null,
        private readonly ?PatternDependencyResolver $dependencies = null,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize();

        return response()->json([
            'success' => true,
            'data' => [
                'patterns' => array_map(fn (Pattern $p): array => $this->manifestResource($p), $this->manager->all()),
                'categories' => $this->manager->categories(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize();

        $pattern = $this->manager->find($id);
        if ($pattern === null) {
            return response()->json(['success' => false, 'message' => 'Pattern not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'manifest' => $this->manifestResource($pattern),
                'payload' => $pattern->payload(),
            ],
        ]);
    }

    /**
     * Resolve a pattern's variables against its payload template (§4/§5) and return
     * the plain Builder payload the editor inserts. Variable values come from the
     * request; anything omitted falls back to the manifest default. Patterns without
     * variables return their payload unchanged.
     */
    public function resolve(string $id): JsonResponse
    {
        $this->authorize();

        $pattern = $this->manager->find($id);
        if ($pattern === null) {
            return response()->json(['success' => false, 'message' => 'Pattern not found.'], 404);
        }

        // Defense-in-depth (Phase 25.2): refuse to serve an insertable payload when
        // the pattern's dependencies are not met. The library already disables the
        // card client-side; this stops a resolved insert from slipping through.
        $availability = $this->dependencyResolver()->check($pattern);
        if (! $availability->available()) {
            return response()->json([
                'success' => false,
                'message' => 'Pattern dependencies are not met.',
                'missing' => $availability->toArray()['missing'],
            ], 422);
        }

        $input = (array) request()->input('variables', []);
        $payload = $this->variableResolver()->resolve($pattern->manifest(), $pattern->payload(), $input);

        return response()->json([
            'success' => true,
            'data' => [
                'manifest' => $this->manifestResource($pattern),
                'payload' => $payload,
            ],
        ]);
    }

    /**
     * List the discovered pattern packs (Phase 25.2, Part 3) with their pattern
     * counts, for grouping the library by pack / theme / plugin source.
     */
    public function packs(): JsonResponse
    {
        $this->authorize();

        $counts = [];
        foreach ($this->manager->all() as $pattern) {
            $packId = $pattern->packId();
            if ($packId !== '') {
                $counts[$packId] = ($counts[$packId] ?? 0) + 1;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'packs' => array_map(
                    static fn (PatternPack $pack): array => [...$pack->toArray(), 'count' => $counts[$pack->id] ?? 0],
                    $this->manager->packs(),
                ),
            ],
        ]);
    }

    /**
     * Stream a pattern's preview image.
     *
     * Contract (§10): this endpoint NEVER returns 500. The pattern is resolved, its
     * thumbnail file is normalised + traversal-checked by {@see PatternPreviewResolver}
     * (one source of truth — no raw-manifest guessing here), and the file is streamed
     * with an EXPLICIT image Content-Type. That explicit type matters beyond
     * correctness: without it a {@see BinaryFileResponse} carries no Content-Type
     * through the middleware stack, and any HTML-injection middleware that treats an
     * empty type as "maybe HTML" then calls string ops on the binary body
     * (`getContent()` === false) and fatals — the real-browser 500. A concrete
     * `image/*` type makes every such middleware skip the response.
     *
     * A pattern that resolves but whose preview file is missing/invalid returns 404
     * (logged), and the Pattern Library card falls back to a built-in placeholder
     * (§13). Any unexpected error is reported and downgraded to 404 — never a 500.
     */
    public function thumbnail(string $id): Response
    {
        $this->authorize();

        $pattern = $this->manager->find($id);
        if ($pattern === null) {
            abort(404);
        }

        try {
            $path = $this->previewResolver()->thumbnailFile($pattern);
        } catch (\Throwable $e) {
            report($e);
            abort(404);
        }

        if ($path === null) {
            abort(404);
        }

        return new BinaryFileResponse($path, 200, [
            'Content-Type' => self::imageMime($path),
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Safe, extension-based image MIME. SVG is served as `image/svg+xml` (a file
     * download, not executed as HTML) and needs no image library (§11). Unknown
     * extensions fall back to a generic binary type so the response is still never
     * mistaken for HTML.
     */
    private static function imageMime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'avif' => 'image/avif',
            default => 'application/octet-stream',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function manifestResource(Pattern $pattern): array
    {
        $preview = $this->previewResolver()->preview($pattern);
        $preview['url'] = ($preview['type'] === 'image' && $preview['available'])
            ? route('page-builder.api.patterns.thumbnail', ['id' => $pattern->id()])
            : null;

        // Human labels resolve for the Admin UI locale (never the editing/frontend
        // locale) at this presentation boundary (PB-FREE-LIBRARY-DESIGN-1-F); every
        // other field stays locale-invariant.
        $manifest = $pattern->manifest();
        $locale = CatalogLocale::admin();
        $fallback = CatalogLocale::fallback();

        return [
            ...$manifest->toArray(),
            'name' => $manifest->localizedName($locale, $fallback),
            'description' => $manifest->localizedDescription($locale, $fallback),
            'thumbnail_url' => route('page-builder.api.patterns.thumbnail', ['id' => $pattern->id()]),
            'preview' => $preview,
            'availability' => $this->dependencyResolver()->check($pattern)->toArray(),
            'pack' => $pattern->packId() !== '' ? $pattern->packId() : null,
        ];
    }

    private function variableResolver(): PatternVariableResolver
    {
        return $this->variables ?? app(PatternVariableResolver::class);
    }

    private function previewResolver(): PatternPreviewResolver
    {
        return $this->previews ?? app(PatternPreviewResolver::class);
    }

    private function dependencyResolver(): PatternDependencyResolver
    {
        return $this->dependencies ?? app(PatternDependencyResolver::class);
    }

    private function authorize(): void
    {
        abort_unless(function_exists('cms_can') && cms_can('page-builder.edit'), 403);
    }
}
