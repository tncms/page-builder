<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Acceptance;

use InvalidArgumentException;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationDecision;

/**
 * The reviewer's explicit decision for one plan item, keyed by origin (LT-7B1, per
 * LT-7-EXECUTION-ARCHITECTURE §1/§5).
 *
 * Layer: Translation (execution contract). Immutable, payload-safe (origin + enum only). It reuses the
 * frozen LT-6 `SynchronizationDecision` — the plan supplies a fail-closed DEFAULT decision; this VO
 * records the reviewer's RESOLVED decision, which execution turns into an operation (AcceptSource) or a
 * no-op (KeepTarget / Ignore).
 */
final class AcceptedItemDecision
{
    public function __construct(
        public readonly string $origin,
        public readonly SynchronizationDecision $decision,
    ) {
        if ($origin === '') {
            throw new InvalidArgumentException('AcceptedItemDecision origin must be a non-empty string.');
        }
    }

    public function isAccepted(): bool
    {
        return $this->decision === SynchronizationDecision::AcceptSource;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'origin' => $this->origin,
            'decision' => $this->decision->value,
        ];
    }
}
