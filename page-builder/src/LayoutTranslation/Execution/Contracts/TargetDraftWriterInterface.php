<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Contracts;

/**
 * Persists the mutated target draft (LT-7B3).
 *
 * Layer: Translation (execution persistence). A thin seam over the canonical `LayoutStore::saveDraft`
 * mutation path. It writes the T-derived document produced by the mutation engine and participates in the
 * coordinator's transaction; it never begins or commits the workflow (LT-7-TRANSACTION-GOVERNANCE).
 */
interface TargetDraftWriterInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function saveDraft(string $ownerType, int|string $ownerId, array $payload): void;
}
