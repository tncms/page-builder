<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

/**
 * The immutable outcome of recovery eligibility evaluation (LT-9D).
 *
 * Layer: Translation (recovery / eligibility). Either eligible — with the set of {@see RecoveryMode}s that
 * are supportable for this candidate — or ineligible, with one or more typed
 * {@see RecoveryIneligibilityReason}s. Read-only; it never mutates, persists, or executes. Determined purely
 * from the immutable candidate + policy + request, so it is deterministic (INV-LT-9D-05).
 */
final class RecoveryEligibility
{
    /**
     * @param  array<int, RecoveryIneligibilityReason>  $reasons
     * @param  array<int, RecoveryMode>  $supportedModes
     */
    private function __construct(
        private readonly bool $eligible,
        private readonly array $reasons,
        private readonly array $supportedModes,
    ) {}

    /**
     * @param  array<int, RecoveryMode>  $supportedModes
     */
    public static function eligible(array $supportedModes): self
    {
        return new self(true, [], array_values($supportedModes));
    }

    /**
     * @param  array<int, RecoveryIneligibilityReason>  $reasons
     * @param  array<int, RecoveryMode>  $supportedModes
     */
    public static function ineligible(array $reasons, array $supportedModes = []): self
    {
        return new self(false, array_values($reasons), array_values($supportedModes));
    }

    public function isEligible(): bool
    {
        return $this->eligible;
    }

    /**
     * @return array<int, RecoveryIneligibilityReason>
     */
    public function reasons(): array
    {
        return $this->reasons;
    }

    public function supports(RecoveryMode $mode): bool
    {
        foreach ($this->supportedModes as $supported) {
            if ($supported === $mode) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, RecoveryMode>
     */
    public function supportedModes(): array
    {
        return $this->supportedModes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'eligible' => $this->eligible,
            'reasons' => array_map(static fn (RecoveryIneligibilityReason $r): string => $r->value, $this->reasons),
            'supported_modes' => array_map(static fn (RecoveryMode $m): string => $m->value, $this->supportedModes),
        ];
    }
}
