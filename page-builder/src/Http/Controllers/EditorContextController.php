<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\PageBuilder\Editor\Translation\EditorOwnerResolutionException;
use Plugins\PageBuilder\Editor\Translation\EffectiveEditorOwnerResolver;
use Plugins\PageBuilder\Editor\Translation\EffectiveLayoutOwner;
use Plugins\PageBuilder\Editor\Translation\FirstOpenLayoutProvisioner;
use Plugins\PageBuilder\Support\EditableOwners;
use Plugins\PageBuilder\Support\EditorContext;
use Plugins\PageBuilder\Support\LayoutNormalizer;
use Plugins\PageBuilder\Support\LayoutStore;
use Plugins\PageBuilder\Models\PageBuilderLayout;
use TheNguyen\CMS\Models\Content;

/**
 * Editor context + draft/publish endpoints for the Vue editor shell (Phase 9;
 * locale-aware since LT-M1B).
 *
 * The URL always addresses the CANONICAL page (`{ownerType}/{ownerId}`); the
 * locale is context, supplied as `?locale=`. The server — never the client —
 * resolves the effective `pb_layouts` owner from the canonical page + locale
 * through {@see EffectiveEditorOwnerResolver}: the source locale keeps the
 * canonical owner (legacy-compatible), a non-default locale resolves to
 * (`content_translation`, ContentTranslation.id). `show` additionally provisions
 * the translated layout on first open (clone + binding + baseline) via the frozen
 * LT pipeline. An invalid/forged locale/translation context fails closed (4xx).
 * All actions require `page-builder.edit` (publish additionally accepts
 * `page-builder.publish`).
 */
final class EditorContextController
{
    public function __construct(
        private readonly LayoutStore $layouts,
        private readonly LayoutNormalizer $normalizer,
        private readonly EditorContext $context,
        private readonly EffectiveEditorOwnerResolver $owners,
        private readonly FirstOpenLayoutProvisioner $provisioner,
    ) {}

    public function show(Request $request, string $ownerType, int|string $ownerId): JsonResponse
    {
        $owner = $this->guardOwner($ownerType, $ownerId, 'page-builder.edit');

        return $this->resolving(function () use ($request, $ownerType, $owner): JsonResponse {
            $effective = $this->owners->resolve($ownerType, $owner->getKey(), $this->localeFrom($request));
            $provision = $this->provisioner->ensure($effective, $this->userId());

            return response()->json([
                'success' => true,
                'data' => $this->context->forResolvedOwner($ownerType, $owner, $effective, $provision),
            ]);
        });
    }

    public function saveDraft(Request $request, string $ownerType, int|string $ownerId): JsonResponse
    {
        $owner = $this->guardOwner($ownerType, $ownerId, 'page-builder.edit');

        return $this->resolving(function () use ($request, $ownerType, $owner): JsonResponse {
            $effective = $this->owners->resolve($ownerType, $owner->getKey(), $this->localeFrom($request));
            // Ensure the target row/binding/baseline exist before writing (idempotent);
            // guarantees a translation write never bypasses the clone pipeline.
            $this->provisioner->ensure($effective, $this->userId());

            $payload = $this->normalizer->normalize(
                is_array($request->input('payload')) ? $request->input('payload') : null,
            );

            $layout = $this->layouts->saveDraft($effective->ownerType, $effective->ownerId, $payload, $this->userId());

            return response()->json([
                'success' => true,
                'data' => $this->context->summarize($layout),
            ]);
        });
    }

    public function publish(Request $request, string $ownerType, int|string $ownerId): JsonResponse
    {
        $owner = $this->guardOwner($ownerType, $ownerId, 'page-builder.publish', 'page-builder.edit');

        return $this->resolving(function () use ($request, $ownerType, $owner): JsonResponse {
            $effective = $this->owners->resolve($ownerType, $owner->getKey(), $this->localeFrom($request));
            $this->provisioner->ensure($effective, $this->userId());

            // Publishing from the editor makes the owner render with the builder on
            // the public frontend (Phase 14.3), so ensure builder mode is set on the
            // resolved (locale-specific) owner — never on the source when editing a
            // translation.
            $this->layouts->setEditorMode($effective->ownerType, $effective->ownerId, PageBuilderLayout::MODE_BUILDER, $this->userId());

            $layout = $this->layouts->publish($effective->ownerType, $effective->ownerId, $this->userId());

            return response()->json([
                'success' => true,
                'data' => $this->context->summarize($layout),
            ]);
        });
    }

    /**
     * Run a resolution closure, turning a fail-closed context rejection into the
     * appropriate 4xx JSON error instead of a 500.
     *
     * @param  callable(): JsonResponse  $callback
     */
    private function resolving(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (EditorOwnerResolutionException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->reason,
                'message' => $e->getMessage(),
            ], $e->statusCode);
        }
    }

    /**
     * The requested editor locale (context only), or null for the source locale.
     * Read from query (GET) or body (POST). Never becomes layout identity by
     * itself — the server re-resolves the owner from the canonical page + locale.
     */
    private function localeFrom(?Request $request): ?string
    {
        $locale = $request?->input('locale');

        if (! is_string($locale)) {
            return null;
        }

        $locale = trim($locale);

        return $locale === '' ? null : $locale;
    }

    /**
     * Authorize (any of the given permissions) and resolve the CANONICAL owner, or
     * abort. Only supported page/post owner types are accepted here; the effective
     * translation owner is derived server-side, never taken from the URL.
     */
    private function guardOwner(string $ownerType, int|string $ownerId, string ...$permissions): Content
    {
        abort_unless($this->canAny(...$permissions), 403);
        abort_unless(EditableOwners::isSupported($ownerType), 404);

        $owner = EditableOwners::resolve($ownerType, $ownerId);
        abort_if($owner === null, 404);

        return $owner;
    }

    private function canAny(string ...$permissions): bool
    {
        if (! function_exists('cms_can')) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (cms_can($permission)) {
                return true;
            }
        }

        return false;
    }

    private function userId(): ?string
    {
        $id = auth()->id();

        return $id === null ? null : (string) $id;
    }
}
