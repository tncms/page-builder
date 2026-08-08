<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Adapters;

use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\TargetDraftWriterInterface;
use Plugins\PageBuilder\Support\LayoutStore;

/**
 * Production target draft writer (LT-7C).
 *
 * Layer: Translation (execution adapter). A thin bridge to the canonical `LayoutStore::saveDraft`
 * mutation path — the same path clone (LT-3) and baseline capture (LT-4) use. It writes the T-derived
 * document produced by the mutation engine and participates in the coordinator's transaction; it never
 * begins or commits it.
 */
final class LayoutStoreTargetDraftWriter implements TargetDraftWriterInterface
{
    public function __construct(private readonly LayoutStore $layouts) {}

    public function saveDraft(string $ownerType, int|string $ownerId, array $payload): void
    {
        $this->layouts->saveDraft($ownerType, $ownerId, $payload);
    }
}
