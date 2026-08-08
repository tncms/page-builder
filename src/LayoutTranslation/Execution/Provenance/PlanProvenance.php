<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Provenance;

/**
 * The content-addressed fingerprints of the exact B, S, T the plan was built from (LT-7B1, per
 * LT-7-EXECUTION-ARCHITECTURE §1 and INV-LT-7-EXEC-01).
 *
 * Layer: Translation (execution contract). Immutable, payload-safe (fingerprints only — no content).
 * It binds an LT-6 plan to the state it describes without modifying the frozen plan contract. Execution
 * verifies current fingerprints against this provenance EXACTLY ONCE, before any mutation begins; any
 * drift aborts as `PlanStale`. This is a pure equality check — it recomputes no diff and reinterprets
 * no policy.
 */
final class PlanProvenance
{
    public function __construct(
        public readonly string $baselineFingerprint,
        public readonly string $sourceFingerprint,
        public readonly string $targetFingerprint,
    ) {}

    /**
     * Verify current fingerprints against this provenance. Returns the first drift in fixed order
     * (baseline → source → target), or null when all three match.
     */
    public function verify(string $baseline, string $source, string $target): ?ProvenanceDrift
    {
        if (! hash_equals($this->baselineFingerprint, $baseline)) {
            return ProvenanceDrift::BaselineChanged;
        }

        if (! hash_equals($this->sourceFingerprint, $source)) {
            return ProvenanceDrift::SourceChanged;
        }

        if (! hash_equals($this->targetFingerprint, $target)) {
            return ProvenanceDrift::TargetChanged;
        }

        return null;
    }

    public function isSatisfiedBy(string $baseline, string $source, string $target): bool
    {
        return $this->verify($baseline, $source, $target) === null;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'baseline_fingerprint' => $this->baselineFingerprint,
            'source_fingerprint' => $this->sourceFingerprint,
            'target_fingerprint' => $this->targetFingerprint,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['baseline_fingerprint'] ?? ''),
            (string) ($data['source_fingerprint'] ?? ''),
            (string) ($data['target_fingerprint'] ?? ''),
        );
    }
}
