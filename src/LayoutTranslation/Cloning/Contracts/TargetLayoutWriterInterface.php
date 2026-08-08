<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts;

use Plugins\PageBuilder\Ownership\LayoutOwnerInterface;

/**
 * Creates a new draft layout document for a generic Layout Owner (LT-3, Option D).
 *
 * Layer: Translation (contract) over the generic Page Builder owner abstraction.
 * It creates the target `pb_layouts` document (draft only; published payload stays
 * empty) and returns its new `pb_layouts.id` — a domain-safe reference, never an
 * Eloquent model. It participates in the caller's transaction; it never begins or
 * commits the workflow. Swappable: this is the seam that realises the Option-D
 * target-owner decision.
 */
interface TargetLayoutWriterInterface
{
    /**
     * @param  array<string, mixed>  $draftPayload
     * @return int  the new pb_layouts.id
     */
    public function createDraftLayoutForOwner(LayoutOwnerInterface $owner, array $draftPayload, ?string $actor = null): int;
}
