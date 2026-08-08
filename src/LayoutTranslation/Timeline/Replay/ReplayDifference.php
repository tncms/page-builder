<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Replay;

/**
 * The immutable, payload-safe description of a replay divergence (LT-9B, per LT-9A §3).
 *
 * Layer: Translation (timeline / replay). Reports *why* a replay diverged — a reason plus the differing
 * origins and the expected/actual fingerprints. It carries fingerprints and origins only, never document
 * content.
 */
final class ReplayDifference
{
    /**
     * @param  array<int, string>  $differingOrigins
     */
    private function __construct(
        public readonly string $reason,
        public readonly array $differingOrigins,
        public readonly ?string $expectedFingerprint,
        public readonly ?string $actualFingerprint,
    ) {}

    public static function planFingerprintMismatch(string $expected, string $actual): self
    {
        return new self('plan_fingerprint_mismatch', [], $expected, $actual);
    }

    /**
     * @param  array<int, string>  $differingOrigins
     */
    public static function documentMismatch(array $differingOrigins, string $expected, string $actual): self
    {
        return new self('document_mismatch', array_values($differingOrigins), $expected, $actual);
    }

    public static function applyFault(string $reason): self
    {
        return new self('apply_fault:'.$reason, [], null, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reason' => $this->reason,
            'differing_origins' => $this->differingOrigins,
            'expected_fingerprint' => $this->expectedFingerprint,
            'actual_fingerprint' => $this->actualFingerprint,
        ];
    }
}
