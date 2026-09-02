<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\PageBuilder\Editor\Translation\EditorOwnerResolutionException;
use Plugins\PageBuilder\Editor\Translation\EffectiveEditorOwnerResolver;
use Plugins\PageBuilder\Editor\Translation\EffectiveLayoutOwner;
use Plugins\PageBuilder\Provenance\AttestationContext;
use Plugins\PageBuilder\Provenance\MaterializationAttestation;
use Plugins\PageBuilder\Support\EditableOwners;
use Plugins\PageBuilder\Support\LayoutStore;

/**
 * The authenticated attestation-lifecycle authority for opaque provenance groups
 * (PB-TREG-2): complete-group renewal and strict-subset narrowing/rebind.
 *
 * Provenance attestations are MINTED only at insert time (mint-at-insert; see
 * EditorContextController::insertRegisteredResource). These two namespaced authenticated
 * POST operations (CSRF via the plugin's web middleware group, `page-builder.edit`,
 * `Cache-Control: no-store, private`) operate on an already-issued opaque group and
 * persist nothing. Every binding field is server-derived; the browser only relays tokens.
 */
final class MaterializationController
{
    public function __construct(
        private readonly EffectiveEditorOwnerResolver $owners,
        private readonly LayoutStore $layouts,
        private readonly MaterializationAttestation $attestation,
    ) {
    }

    /** POST — renew a complete attestation group (operational expiry only). */
    public function renew(Request $request, string $ownerType, int|string $ownerId): JsonResponse
    {
        return $this->authorized($request, $ownerType, $ownerId, function (EffectiveLayoutOwner $effective) use ($request): JsonResponse {
            $renewed = $this->attestation->renewGroup($this->tokensFrom($request), $this->context($effective));
            if ($renewed === null) {
                return $this->error('pb_provenance_renewal_failed', 422);
            }

            return $this->noStore(['success' => true, 'data' => ['attestations' => $renewed]]);
        });
    }

    /** POST — narrow/rebind a group to a strict subset of its original roots. */
    public function rebind(Request $request, string $ownerType, int|string $ownerId): JsonResponse
    {
        return $this->authorized($request, $ownerType, $ownerId, function (EffectiveLayoutOwner $effective) use ($request): JsonResponse {
            $retained = $request->input('retainedRootIds');
            $result = $this->attestation->rebindGroup(
                $this->tokensFrom($request),
                is_array($retained) ? array_values($retained) : [],
                $this->context($effective),
            );

            if ($result === 'discard') {
                return $this->noStore(['success' => true, 'data' => ['discarded' => true, 'attestations' => []]]);
            }
            if ($result === null) {
                return $this->error('pb_provenance_rebind_failed', 422);
            }

            return $this->noStore(['success' => true, 'data' => ['attestations' => $result]]);
        });
    }

    /**
     * @param  callable(EffectiveLayoutOwner): JsonResponse  $callback
     */
    private function authorized(Request $request, string $ownerType, int|string $ownerId, callable $callback): JsonResponse
    {
        abort_unless(function_exists('cms_can') && cms_can('page-builder.edit'), 403);
        abort_unless(EditableOwners::isSupported($ownerType), 404);
        $owner = EditableOwners::resolve($ownerType, $ownerId);
        abort_if($owner === null, 404);

        try {
            $effective = $this->owners->resolve($ownerType, $owner->getKey(), $this->localeFrom($request));

            return $callback($effective);
        } catch (EditorOwnerResolutionException $e) {
            return response()->json(['success' => false, 'error' => $e->reason, 'message' => $e->getMessage()], $e->statusCode);
        }
    }

    private function context(EffectiveLayoutOwner $effective): AttestationContext
    {
        return new AttestationContext(
            (string) (auth()->id() ?? ''),
            AttestationContext::owner($effective->ownerType, $effective->ownerId),
            $this->layouts->currentToken($effective->ownerType, $effective->ownerId),
        );
    }

    /**
     * @return array<int, string>
     */
    private function tokensFrom(Request $request): array
    {
        $raw = $request->input('attestations');

        return is_array($raw) ? array_values($raw) : [];
    }

    private function localeFrom(Request $request): ?string
    {
        $locale = $request->input('locale');
        $locale = is_string($locale) ? trim($locale) : '';

        return $locale === '' ? null : $locale;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function noStore(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'no-store, private');
    }

    private function error(string $code, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $code], $status)->header('Cache-Control', 'no-store, private');
    }
}
