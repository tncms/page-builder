<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Decision;

use InvalidArgumentException;
use Plugins\PageBuilder\LayoutTranslation\Execution\Acceptance\AcceptedItemDecision;
use Plugins\PageBuilder\LayoutTranslation\Execution\Acceptance\SynchronizationAcceptanceSet;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationDecision;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationPlan;

/**
 * The reviewer's working, per-origin decisions (LT-8B, per LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §5 and
 * LT-8A-FINAL-GOVERNANCE INV-LT-8A-07/08).
 *
 * Layer: Translation (review command / working state). This is the COMMAND side of CQRS and the ONE place
 * reviewer intent lives (INV-LT-8A-08). It is seeded fail-closed from the plan's DEFAULT decisions
 * (INV-LT-8A-05), edited per origin, and lowered at submit to the frozen {@see
 * SynchronizationAcceptanceSet}. With the {@see SynchronizationPlan} it is the ONLY authoritative state a
 * disposable session reconstructs from (INV-LT-8A-07). Payload-safe: origins + the frozen
 * {@see SynchronizationDecision} enum only — never content.
 */
final class DecisionSet
{
    /**
     * @param  array<string, SynchronizationDecision>  $decisions
     * @param  array<string, SynchronizationDecision>  $defaults
     * @param  array<string, true>                     $touched
     */
    private function __construct(
        private array $decisions,
        private readonly array $defaults,
        private array $touched,
    ) {}

    /**
     * Seed one decision per plan origin from the plan's fail-closed default (LT-6). An untouched set
     * therefore already encodes the safe, non-destructive resolution.
     */
    public static function seedFromPlan(SynchronizationPlan $plan): self
    {
        $decisions = [];
        $defaults = [];

        foreach ($plan->items() as $item) {
            $decisions[$item->origin] = $item->defaultDecision;
            $defaults[$item->origin] = $item->defaultDecision;
        }

        return new self($decisions, $defaults, []);
    }

    /**
     * Reconstruct working decisions against a (re-derived) plan on resume (LT-8A §8.2, INV-LT-8A-07).
     * Seeds fail-closed from the plan, then re-applies only the reviewer's TOUCHED decisions by origin:
     * origins no longer in the plan are dropped; new origins keep their fail-closed default.
     *
     * @param  array<string, array{decision:string, touched:bool}>  $snapshot
     */
    public static function reconstruct(SynchronizationPlan $plan, array $snapshot): self
    {
        $set = self::seedFromPlan($plan);

        foreach ($snapshot as $origin => $entry) {
            if (! $set->has((string) $origin)) {
                continue; // drop orphaned decision (fail-closed)
            }
            if (empty($entry['touched'])) {
                continue; // untouched stays on the fresh default
            }
            $decision = SynchronizationDecision::tryFrom((string) ($entry['decision'] ?? ''));
            if ($decision !== null) {
                $set->record((string) $origin, $decision);
            }
        }

        return $set;
    }

    public function record(string $origin, SynchronizationDecision $decision): void
    {
        $this->assertKnown($origin);
        $this->decisions[$origin] = $decision;
        $this->touched[$origin] = true;
    }

    public function resetToDefault(string $origin): void
    {
        $this->assertKnown($origin);
        $this->decisions[$origin] = $this->defaults[$origin];
        unset($this->touched[$origin]);
    }

    public function decisionFor(string $origin): ?SynchronizationDecision
    {
        return $this->decisions[$origin] ?? null;
    }

    public function defaultFor(string $origin): ?SynchronizationDecision
    {
        return $this->defaults[$origin] ?? null;
    }

    public function isDecided(string $origin): bool
    {
        return isset($this->touched[$origin]);
    }

    public function has(string $origin): bool
    {
        return array_key_exists($origin, $this->defaults);
    }

    /**
     * @return array<int, string>
     */
    public function origins(): array
    {
        return array_keys($this->defaults);
    }

    /**
     * Lower to the frozen acceptance contract — one {@see AcceptedItemDecision} per plan origin (total,
     * no gaps, no extras; matches the coordinator's AcceptanceValidator).
     */
    public function toAcceptance(): SynchronizationAcceptanceSet
    {
        $items = [];
        foreach ($this->decisions as $origin => $decision) {
            $items[] = new AcceptedItemDecision((string) $origin, $decision);
        }

        return new SynchronizationAcceptanceSet($items);
    }

    /**
     * A payload-safe, reconstruction-sufficient snapshot (INV-LT-8A-07). Origins + enums + a touched flag
     * only — never the plan payload and never content.
     *
     * @return array<string, array{decision:string, touched:bool}>
     */
    public function snapshot(): array
    {
        $out = [];
        foreach ($this->decisions as $origin => $decision) {
            $out[(string) $origin] = [
                'decision' => $decision->value,
                'touched' => isset($this->touched[$origin]),
            ];
        }

        return $out;
    }

    public function isEmpty(): bool
    {
        return $this->defaults === [];
    }

    public function count(): int
    {
        return count($this->defaults);
    }

    private function assertKnown(string $origin): void
    {
        if (! array_key_exists($origin, $this->defaults)) {
            throw new InvalidArgumentException("Decision for unknown plan origin: {$origin}");
        }
    }
}
