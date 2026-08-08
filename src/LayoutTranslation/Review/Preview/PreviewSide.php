<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Preview;

/**
 * One renderable side (B / S / T) of a preview fragment (LT-8B, per
 * LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §6).
 *
 * Layer: Translation (review preview / value). Read-only (INV-LT-8A-08) and content-BEARING but strictly
 * one-directional (INV-LT-8A-02): it exists only to be rendered for a human. It is never assigned into a
 * ReviewNode, a DecisionSet, an AcceptedItemDecision, the command, the coordinator, or a session snapshot.
 * It exposes no apply()/save()/accept()/reject().
 */
final class PreviewSide
{
    public function __construct(
        public readonly string $render,
    ) {}
}
