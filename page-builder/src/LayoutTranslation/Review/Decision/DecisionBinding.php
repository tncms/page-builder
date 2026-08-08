<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Decision;

use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationDecision;

/**
 * Routes reviewer intent into the working {@see DecisionSet} (LT-8B, per
 * LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §4.1/§5.1).
 *
 * Layer: Translation (review command). The COMMAND-side binder that turns a UI decision event into a
 * write on the one authoritative intent store (INV-LT-8A-08). It performs no projection and holds no
 * derived state (INV-LT-8A-07); it only records or resets a decision, always drawn from the frozen
 * {@see SynchronizationDecision} set (INV-LT-8A-03). Content never reaches it (INV-LT-8A-02).
 */
final class DecisionBinding
{
    public function __construct(
        private readonly DecisionSet $decisions,
    ) {}

    public function record(string $origin, SynchronizationDecision $decision): void
    {
        $this->decisions->record($origin, $decision);
    }

    public function resetToDefault(string $origin): void
    {
        $this->decisions->resetToDefault($origin);
    }

    /**
     * @param  array<string, SynchronizationDecision>  $decisionsByOrigin
     */
    public function recordMany(array $decisionsByOrigin): void
    {
        foreach ($decisionsByOrigin as $origin => $decision) {
            $this->decisions->record((string) $origin, $decision);
        }
    }
}
