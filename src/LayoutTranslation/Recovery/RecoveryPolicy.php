<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

/**
 * The declarative policy that governs which recovery strategies are permitted (LT-9D).
 *
 * Layer: Translation (recovery / policy). Immutable and framework-free. It states which {@see RecoveryMode}s
 * are allowed, whether soft conflicts may be overridden, and whether the reserved AI-assisted strategy is
 * enabled (it is not, in this phase). Policy is an input to deterministic eligibility and conflict analysis —
 * it never reads storage and never executes.
 */
final class RecoveryPolicy
{
    /**
     * @param  array<int, RecoveryMode>  $allowedModes
     */
    private function __construct(
        private readonly array $allowedModes,
        private readonly bool $overrideAllowed,
        private readonly bool $aiAssistedEnabled,
    ) {}

    /**
     * @param  array<int, RecoveryMode>  $allowedModes
     */
    public static function of(array $allowedModes, bool $overrideAllowed = true, bool $aiAssistedEnabled = false): self
    {
        return new self(array_values($allowedModes), $overrideAllowed, $aiAssistedEnabled);
    }

    /** The conservative default: every non-reserved strategy, overrides allowed, AI-assisted off. */
    public static function default(): self
    {
        return new self(
            [RecoveryMode::ReplayOnly, RecoveryMode::ReExecute, RecoveryMode::Retry, RecoveryMode::Manual],
            true,
            false,
        );
    }

    public function allows(RecoveryMode $mode): bool
    {
        if ($mode->isReserved() && ! $this->aiAssistedEnabled) {
            return false;
        }

        foreach ($this->allowedModes as $allowed) {
            if ($allowed === $mode) {
                return true;
            }
        }

        return false;
    }

    public function allowsOverride(): bool
    {
        return $this->overrideAllowed;
    }

    /**
     * @return array<int, RecoveryMode>
     */
    public function allowedModes(): array
    {
        return $this->allowedModes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allowed_modes' => array_map(static fn (RecoveryMode $m): string => $m->value, $this->allowedModes),
            'override_allowed' => $this->overrideAllowed,
            'ai_assisted_enabled' => $this->aiAssistedEnabled,
        ];
    }
}
