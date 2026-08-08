<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Adapters;

use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TargetLayoutWriterInterface;
use Plugins\PageBuilder\Models\PageBuilderLayout;
use Plugins\PageBuilder\Ownership\LayoutOwnerInterface;
use Plugins\PageBuilder\Support\LayoutStore;

/**
 * Creates the target draft layout document for a generic Layout Owner (LT-3,
 * Option D).
 *
 * Layer: Translation (adapter) over the generic Page Builder {@see LayoutStore}
 * (the single canonical layout-document store — no second store is created). It
 * writes the cloned payload as the target's `draft_payload` (the target begins a
 * draft; `published_payload` stays empty) and sets builder mode, then returns the
 * new `pb_layouts.id`. It returns a domain-safe int, never an Eloquent model, and
 * participates in the caller's transaction (it opens no transaction of its own).
 * This is the swappable realisation of the Option-D target-owner decision.
 */
final class PageBuilderTargetLayoutWriter implements TargetLayoutWriterInterface
{
    public function __construct(private readonly LayoutStore $layouts) {}

    public function createDraftLayoutForOwner(LayoutOwnerInterface $owner, array $draftPayload, ?string $actor = null): int
    {
        $this->layouts->saveDraft($owner->ownerType(), $owner->ownerId(), $draftPayload, $actor);
        $layout = $this->layouts->setEditorMode($owner->ownerType(), $owner->ownerId(), PageBuilderLayout::MODE_BUILDER, $actor);

        return (int) $layout->getKey();
    }
}
