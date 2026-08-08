<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Acceptance;

use InvalidArgumentException;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationDecision;

/**
 * The reviewer's explicit, per-item decisions for a plan (LT-7B1, per LT-7-EXECUTION-ARCHITECTURE §1).
 *
 * Layer: Translation (execution contract). Immutable, payload-safe, deterministic (origins sorted). One
 * decision per origin — a duplicate is a construction error. This is the reviewer authority that turns
 * the LT-6 plan's DEFAULT decisions into resolved ones; execution validates it against the plan (every
 * decided origin must exist in the plan) before building operations. It carries no content, no Source,
 * Target, Baseline, repository, callback or closure.
 */
final class SynchronizationAcceptanceSet
{
    /** @var array<string, AcceptedItemDecision> */
    private readonly array $decisions;

    /**
     * @param  array<int, AcceptedItemDecision>  $decisions
     */
    public function __construct(array $decisions)
    {
        $byOrigin = [];
        foreach ($decisions as $decision) {
            if (isset($byOrigin[$decision->origin])) {
                throw new InvalidArgumentException("Duplicate decision for origin: {$decision->origin}");
            }
            $byOrigin[$decision->origin] = $decision;
        }

        ksort($byOrigin);

        $this->decisions = $byOrigin;
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function decisionFor(string $origin): ?AcceptedItemDecision
    {
        return $this->decisions[$origin] ?? null;
    }

    public function has(string $origin): bool
    {
        return isset($this->decisions[$origin]);
    }

    /**
     * @return array<int, string>
     */
    public function origins(): array
    {
        return array_keys($this->decisions);
    }

    /**
     * @return array<int, string>
     */
    public function acceptedOrigins(): array
    {
        $accepted = [];
        foreach ($this->decisions as $origin => $decision) {
            if ($decision->decision === SynchronizationDecision::AcceptSource) {
                $accepted[] = $origin;
            }
        }

        return $accepted;
    }

    /**
     * @return array<int, AcceptedItemDecision>
     */
    public function all(): array
    {
        return array_values($this->decisions);
    }

    public function isEmpty(): bool
    {
        return $this->decisions === [];
    }

    public function count(): int
    {
        return count($this->decisions);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function toArray(): array
    {
        return array_map(static fn (AcceptedItemDecision $d): array => $d->toArray(), array_values($this->decisions));
    }
}
