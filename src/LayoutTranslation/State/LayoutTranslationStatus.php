<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\State;

use InvalidArgumentException;

/**
 * The composite, multi-dimensional state of a layout translation — LT-1 (locked).
 *
 * Three independent dimensions — existence, lifecycle, synchronization — held in
 * one immutable value object. It replaces the previous flat single-enum state
 * machine. Cross-dimension consistency is enforced at construction so an
 * impossible combination cannot exist:
 *
 *   - lifecycle is present iff existence is Exists;
 *   - synchronization is NotApplicable unless existence is Exists.
 *
 * `null` lifecycle is not a lifecycle value — it means "no lifecycle because no
 * layout exists", which the existence dimension already states explicitly.
 */
final class LayoutTranslationStatus
{
    private function __construct(
        public readonly LayoutTranslationExistence $existence,
        public readonly ?LayoutTranslationLifecycle $lifecycle,
        public readonly LayoutSynchronizationState $synchronization,
    ) {
        $exists = $existence === LayoutTranslationExistence::Exists;

        if ($exists && $lifecycle === null) {
            throw new InvalidArgumentException('An existing layout must carry a lifecycle.');
        }

        if (! $exists && $lifecycle !== null) {
            throw new InvalidArgumentException('A non-existent layout must not carry a lifecycle.');
        }

        if (! $exists && $synchronization !== LayoutSynchronizationState::NotApplicable) {
            throw new InvalidArgumentException('Synchronization applies only to an existing layout.');
        }
    }

    public static function notCreated(): self
    {
        return new self(LayoutTranslationExistence::NotCreated, null, LayoutSynchronizationState::NotApplicable);
    }

    public static function invalidReference(): self
    {
        return new self(LayoutTranslationExistence::InvalidReference, null, LayoutSynchronizationState::NotApplicable);
    }

    public static function exists(
        LayoutTranslationLifecycle $lifecycle,
        LayoutSynchronizationState $synchronization = LayoutSynchronizationState::NotApplicable,
    ): self {
        return new self(LayoutTranslationExistence::Exists, $lifecycle, $synchronization);
    }

    public function existence(): LayoutTranslationExistence
    {
        return $this->existence;
    }

    public function lifecycle(): ?LayoutTranslationLifecycle
    {
        return $this->lifecycle;
    }

    public function synchronization(): LayoutSynchronizationState
    {
        return $this->synchronization;
    }

    public function hasLayout(): bool
    {
        return $this->existence === LayoutTranslationExistence::Exists;
    }

    /**
     * Re-derive the cross-dimension consistency rules (defensive; the constructor
     * already guarantees them). Used by the validator to assert the invariant.
     */
    public function isConsistent(): bool
    {
        $exists = $this->existence === LayoutTranslationExistence::Exists;

        if ($exists !== ($this->lifecycle !== null)) {
            return false;
        }

        return $exists || $this->synchronization === LayoutSynchronizationState::NotApplicable;
    }

    public function equals(self $other): bool
    {
        return $this->existence === $other->existence
            && $this->lifecycle === $other->lifecycle
            && $this->synchronization === $other->synchronization;
    }

    /**
     * @return array<string, ?string>
     */
    public function toArray(): array
    {
        return [
            'existence' => $this->existence->value,
            'lifecycle' => $this->lifecycle?->value,
            'synchronization' => $this->synchronization->value,
        ];
    }
}
