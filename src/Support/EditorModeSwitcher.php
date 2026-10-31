<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Plugins\PageBuilder\Authority\ContentAuthority;
use Plugins\PageBuilder\Editor\Translation\EffectiveEditorOwnerResolver;
use Plugins\PageBuilder\Models\PageBuilderLayout;
use TheNguyen\CMS\Models\Content;

/**
 * The single interactive write path for the editor mode / public Render Source
 * (PB-CORE-AUTHORITY-REMEDIATION-R1; ADR-PB-RENDER-SOURCE-001).
 *
 * The mode only SELECTS the renderer. Classic content (Core) and the layout (PB)
 * are independent durable sources: a switch writes `pb_layouts.editor_mode` and
 * nothing else — it never rewrites, nulls, imports, flattens, deletes or publishes
 * either source.
 *
 * Every switch is:
 *   - authorized: publication authority when the layout has a published payload
 *     (the switch changes public output), draft authority otherwise;
 *   - one Core content unit against the caller's Core revision (Core row locked
 *     first; no nested Core write, so the revision does not move);
 *   - a compare-and-swap on the layout's publication token (mode + published
 *     payload; draft saves do not count). A missing or stale token throws
 *     {@see LayoutVersionConflictException} with zero writes, so two sessions that
 *     opened the same state produce exactly one winner.
 *
 * Used by the Page/Post form "Rendering" control, the editor pages and the
 * editor JSON endpoint.
 */
final class EditorModeSwitcher
{
    public function __construct(
        private readonly EffectiveEditorOwnerResolver $owners,
        private readonly LayoutStore $layouts,
        private readonly ContentAuthority $authority,
    ) {}

    /**
     * Switch the effective owner of $canonical in $locale (fail-closed resolution:
     * a bad locale never retargets the source owner).
     *
     * @throws \Plugins\PageBuilder\Editor\Translation\EditorOwnerResolutionException
     * @throws \Plugins\PageBuilder\Authority\PageBuilderAuthorityException
     * @throws \TheNguyen\CMS\ContentAuthority\ContentWriteException
     * @throws LayoutVersionConflictException
     */
    public function switch(
        Content $canonical,
        ?string $locale,
        RenderSource $target,
        ?Authenticatable $actor,
        ?int $expectedRevision,
        ?string $expectedPublicationToken,
    ): PageBuilderLayout {
        $effective = $this->owners->resolve((string) $canonical->getAttribute('type'), $canonical->getKey(), $locale);

        return $this->switchOwner($canonical, $effective->ownerType, $effective->ownerId, $target, $actor, $expectedRevision, $expectedPublicationToken);
    }

    /**
     * Switch an already-resolved layout owner of $canonical (the editor pages hold
     * the effective owner they were opened for).
     *
     * @throws \Plugins\PageBuilder\Authority\PageBuilderAuthorityException
     * @throws \TheNguyen\CMS\ContentAuthority\ContentWriteException
     * @throws LayoutVersionConflictException
     */
    public function switchOwner(
        Content $canonical,
        string $layoutOwnerType,
        int|string $layoutOwnerId,
        RenderSource $target,
        ?Authenticatable $actor,
        ?int $expectedRevision,
        ?string $expectedPublicationToken,
    ): PageBuilderLayout {
        $published = (bool) $this->layouts->find($layoutOwnerType, $layoutOwnerId)?->hasPublished();

        $published
            ? $this->authority->assertCanPublish($canonical, $actor)
            : $this->authority->assertCanEdit($canonical, $actor);

        if ($expectedPublicationToken === null || $expectedPublicationToken === '') {
            $current = $this->layouts->currentPublicationToken($layoutOwnerType, $layoutOwnerId);

            throw new LayoutVersionConflictException($current, [
                'publication_token' => $current,
                'reason' => 'token_required',
            ]);
        }

        /** @var Authenticatable $actor the assertion refused a guest */
        return $this->authority->withContentUnit($canonical, $expectedRevision, $actor, fn (): PageBuilderLayout => $this->layouts->setEditorMode(
            $layoutOwnerType,
            $layoutOwnerId,
            $target->toStorage(),
            // Not a draft edit: the draft's last-editor stamp stays truthful.
            null,
            $expectedPublicationToken,
        ), recheck: $published ? ContentAuthority::PUBLISH : null);
    }
}
