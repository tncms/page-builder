<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\PageBuilder\Editor\Translation\EditorOwnerResolutionException;
use Plugins\PageBuilder\Editor\Translation\EffectiveEditorOwnerResolver;
use Plugins\PageBuilder\Editor\Translation\EffectiveLayoutOwner;
use Plugins\PageBuilder\Editor\Translation\FirstOpenLayoutProvisioner;
use Plugins\PageBuilder\Provenance\AttestationContext;
use Plugins\PageBuilder\Provenance\ProvenanceReconciler;
use Plugins\PageBuilder\Provenance\ProvenanceReconciliationException;
use Plugins\PageBuilder\Provenance\RegisteredResourceInsertion;
use Plugins\PageBuilder\Provenance\RegisteredResourceInsertionException;
use Plugins\PageBuilder\Support\EditableOwners;
use Plugins\PageBuilder\Support\EditorContext;
use Plugins\PageBuilder\Support\LayoutNormalizer;
use Plugins\PageBuilder\Support\LayoutStore;
use Plugins\PageBuilder\Support\LayoutVersionConflictException;
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
        private readonly ProvenanceReconciler $reconciler,
        private readonly RegisteredResourceInsertion $insertion,
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

            // CAS order (PB-TREG-2): the stale-token gate runs BEFORE any provenance work,
            // so a stale request 409s without reconciling/verifying/stamping anything. The
            // authoritative in-transaction CAS in LayoutStore::saveDraft is the final guard.
            $expectedToken = $this->requireToken($request, $effective);
            $currentToken = $this->layouts->currentToken($effective->ownerType, $effective->ownerId);
            if (! hash_equals($currentToken, $expectedToken)) {
                throw new LayoutVersionConflictException($currentToken, [
                    'token' => $currentToken,
                    'reason' => 'stale_token',
                ]);
            }

            // Normalize strips ALL raw browser metadata; the reconciler is the sole
            // authority that re-attaches trusted provenance (preserve by server identity /
            // stamp verified materialization groups). A bad group rejects the whole save.
            $normalized = $this->normalizer->normalize(
                is_array($request->input('payload')) ? $request->input('payload') : null,
            );
            $context = new AttestationContext(
                (string) ($this->userId() ?? ''),
                AttestationContext::owner($effective->ownerType, $effective->ownerId),
                $expectedToken,
            );
            $reconciled = $this->reconciler->reconcileDraft(
                $this->layouts->getDraftPayload($effective->ownerType, $effective->ownerId),
                $normalized,
                $this->attestationsFrom($request),
                $context,
            );

            $layout = $this->layouts->saveDraft(
                $effective->ownerType,
                $effective->ownerId,
                $reconciled,
                $this->userId(),
                $expectedToken,
            );

            return response()->json([
                'success' => true,
                'data' => $this->context->summarize($layout),
            ]);
        });
    }

    /**
     * Insert a registered registry resource (pattern/block) into the client's current
     * unsaved editor state and mint provenance from the authoritative command result
     * (PB-TREG-2 mint-at-insert). Orchestration only: it dispatches the EXISTING insert
     * commands through the one CommandBus and PERSISTS NOTHING — the returned state is
     * adopted by the client exactly as after any command, and the opaque attestation
     * group rides to the later saveDraft. The token baseline is verified first; the final
     * save CAS remains the race authority.
     */
    public function insertRegisteredResource(Request $request, string $ownerType, int|string $ownerId): JsonResponse
    {
        $owner = $this->guardOwner($ownerType, $ownerId, 'page-builder.edit');

        return $this->resolving(function () use ($request, $ownerType, $owner): JsonResponse {
            $effective = $this->owners->resolve($ownerType, $owner->getKey(), $this->localeFrom($request));
            $this->provisioner->ensure($effective, $this->userId());

            // Token baseline verified BEFORE any registry resolution/insertion.
            $expectedToken = $this->requireToken($request, $effective);
            $currentToken = $this->layouts->currentToken($effective->ownerType, $effective->ownerId);
            if (! hash_equals($currentToken, $expectedToken)) {
                throw new LayoutVersionConflictException($currentToken, ['token' => $currentToken, 'reason' => 'stale_token']);
            }

            $context = new AttestationContext(
                (string) ($this->userId() ?? ''),
                AttestationContext::owner($effective->ownerType, $effective->ownerId),
                $expectedToken,
            );

            $target = $request->input('target_parent_id');
            $index = $request->input('index');
            $result = $this->insertion->insert(
                (string) $request->input('rid', ''),
                is_array($request->input('payload')) ? $request->input('payload') : null,
                $context,
                is_string($target) && $target !== '' ? $target : null,
                is_numeric($index) ? (int) $index : null,
            );

            return response()->json([
                'success' => true,
                'data' => array_merge($result['state'], [
                    'artifacts' => [
                        'provenanceAttestations' => $result['attestations'],
                        'rootIds' => $result['rootIds'],
                        'expiresAt' => $result['expiresAt'],
                        'horizonAt' => $result['horizonAt'],
                    ],
                ]),
            ])->header('Cache-Control', 'no-store, private');
        });
    }

    public function publish(Request $request, string $ownerType, int|string $ownerId): JsonResponse
    {
        $owner = $this->guardOwner($ownerType, $ownerId, 'page-builder.publish', 'page-builder.edit');

        return $this->resolving(function () use ($request, $ownerType, $owner): JsonResponse {
            $effective = $this->owners->resolve($ownerType, $owner->getKey(), $this->localeFrom($request));
            $this->provisioner->ensure($effective, $this->userId());

            // Publishing from the editor makes the owner render with the builder on the
            // public frontend (Phase 14.3). LayoutStore::publish sets builder mode and
            // copies draft → published in ONE token-checked transaction on the resolved
            // (locale-specific) owner — never the source when editing a translation — so
            // a stale publish (INV-PUB-06) cannot slip between a mode set and the copy.
            $layout = $this->layouts->publishAsBuilder(
                $effective->ownerType,
                $effective->ownerId,
                $this->userId(),
                $this->requireToken($request, $effective),
            );

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
        } catch (LayoutVersionConflictException $e) {
            // INV-PUB-06 — a stale save/publish is a truthful 409 conflict carrying the
            // current-version metadata for safe reload/reconciliation (no payload, no
            // auto-merge, no silent overwrite). Authorization already passed in
            // guardOwner(), so this never discloses state to an unauthorized actor.
            return response()->json([
                'success' => false,
                'error' => LayoutVersionConflictException::ERROR_CODE,
                'message' => $e->getMessage(),
                'data' => $e->current,
            ], 409);
        } catch (ProvenanceReconciliationException $e) {
            // A malformed/forged/expired/incomplete/conflicting materialization group
            // rejects the whole save with a bounded, namespaced machine code — never a
            // signature, key, token body, or path. The client keeps its unsaved payload
            // and may re-open/renew (see the provenance transport contract).
            return response()->json([
                'success' => false,
                'error' => 'pb_provenance_'.$e->reason,
                'message' => __('This inserted design could not be verified. Please re-open the resource browser and insert it again.'),
            ], 422);
        } catch (RegisteredResourceInsertionException $e) {
            // A registered-resource insertion failed (unknown/unavailable resource,
            // invalid target/position, or an inconsistent authoritative result). Nothing
            // was persisted; the client keeps its unsaved state.
            return response()->json([
                'success' => false,
                'error' => 'pb_insert_'.$e->reason,
                'message' => __('This design could not be inserted. Please try again.'),
            ], 422);
        }
    }

    /**
     * The relayed opaque provenance attestation tokens (transport only) as a list. A
     * non-list yields an empty set; the reconciler enforces the authoritative count/byte
     * bounds and rejects any non-string entry — the client never makes a trust decision.
     *
     * @return array<int, string>
     */
    private function attestationsFrom(Request $request): array
    {
        $raw = $request->input('provenanceAttestations');

        return is_array($raw) ? array_values($raw) : [];
    }

    /**
     * The version token the editor MUST carry on a save/publish (INV-PUB-06). An
     * absent/empty token cannot bypass optimistic concurrency at the HTTP boundary —
     * even though {@see LayoutStore}'s internal seam permits a null token for trusted
     * server-side callers — so a missing token is rejected as a conflict carrying the
     * current authoritative token for reconciliation (zero mutation; thrown before any
     * write, after authorization).
     *
     * @throws LayoutVersionConflictException when the request omits a usable token.
     */
    private function requireToken(Request $request, EffectiveLayoutOwner $effective): string
    {
        $token = $this->tokenFrom($request);

        if ($token !== null) {
            return $token;
        }

        $current = $this->layouts->currentToken($effective->ownerType, $effective->ownerId);

        throw new LayoutVersionConflictException($current, [
            'token' => $current,
            'reason' => 'token_required',
        ]);
    }

    /**
     * The raw optimistic-concurrency token from the request, or null when absent/empty.
     */
    private function tokenFrom(Request $request): ?string
    {
        $token = $request->input('token');

        return is_string($token) && $token !== '' ? $token : null;
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
