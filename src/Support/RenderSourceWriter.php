<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use Plugins\PageBuilder\Editor\Translation\EditorOwnerResolutionException;
use Plugins\PageBuilder\Editor\Translation\EffectiveEditorOwnerResolver;

/**
 * Writes/reads the persisted Render Source for the EFFECTIVE owner of a canonical
 * page in a given locale (ADR-PB-RENDER-SOURCE-001, PB-RS-1).
 *
 * It exists only to orchestrate two already-existing services:
 *   1. {@see EffectiveEditorOwnerResolver} — the frozen locale → effective-owner
 *      resolution (LT-M1B). The WRITE path uses fail-closed {@see
 *      EffectiveEditorOwnerResolver::resolve()} so a Render Source mutation can
 *      never silently retarget the canonical/source owner on a bad locale
 *      (ADR-PB-EFFECTIVE-LAYOUT-OWNER-RESOLUTION, INV-PB-OWNER-05); the READ path
 *      uses fail-soft {@see EffectiveEditorOwnerResolver::resolveForReadOrCanonical()}.
 *   2. {@see LayoutStore::setEditorMode()} — the existing storage gateway.
 *
 * Because the effective owner is per locale (default → canonical page; non-default
 * → content_translation), setting EN's source never touches VI's and vice versa.
 * This service NEVER publishes, clones, renders, resolves translations manually,
 * or reaches into Layout Translation internals.
 */
final class RenderSourceWriter
{
    public function __construct(
        private readonly EffectiveEditorOwnerResolver $owners,
        private readonly LayoutStore $layouts,
    ) {}

    /**
     * Persist the Render Source for the effective owner of ($canonicalType,
     * $canonicalId) in $locale.
     *
     * Fail-closed: this is a mutation, so it resolves the effective owner through
     * {@see EffectiveEditorOwnerResolver::resolve()} — an invalid / disabled /
     * out-of-group locale throws {@see EditorOwnerResolutionException} instead of
     * silently falling back to the canonical (source) owner, so a translation's
     * Render Source write can never land on the source layout (INV-PB-OWNER-05).
     *
     * @throws EditorOwnerResolutionException on an unresolvable locale context.
     */
    public function setForCanonical(
        string $canonicalType,
        int|string $canonicalId,
        ?string $locale,
        RenderSource $source,
        ?string $actorId = null,
    ): void {
        $effective = $this->owners->resolve($canonicalType, $canonicalId, $locale);

        $this->layouts->setEditorMode($effective->ownerType, $effective->ownerId, $source->toStorage(), $actorId);
    }

    /**
     * The current Render Source for the effective owner of ($canonicalType,
     * $canonicalId) in $locale. Fails closed to Classic when no layout row exists.
     */
    public function currentForCanonical(
        string $canonicalType,
        int|string $canonicalId,
        ?string $locale,
    ): RenderSource {
        [$ownerType, $ownerId] = $this->owners->resolveForReadOrCanonical($canonicalType, $canonicalId, $locale);

        return $this->layouts->find($ownerType, $ownerId)?->renderSource() ?? RenderSource::Classic;
    }
}
