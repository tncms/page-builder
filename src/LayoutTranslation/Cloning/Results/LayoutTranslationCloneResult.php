<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Results;

use Plugins\PageBuilder\LayoutTranslation\Persistence\Results\PersistedLayoutTranslationBinding;

/**
 * The immutable, typed result of a clone attempt (LT-3).
 *
 * Layer: Translation (clone result). Exposes only safe domain information: the
 * typed outcome, the created target binding (on success), and an optional
 * non-sensitive detail. It never exposes Eloquent models, raw exceptions, SQL,
 * query builders or database rows. It does not touch the frozen LT-1 descriptor.
 */
final class LayoutTranslationCloneResult
{
    private function __construct(
        public readonly LayoutTranslationCloneOutcome $outcome,
        public readonly ?PersistedLayoutTranslationBinding $binding,
        public readonly ?string $detail,
    ) {}

    public static function created(PersistedLayoutTranslationBinding $binding): self
    {
        return new self(LayoutTranslationCloneOutcome::Created, $binding, null);
    }

    public static function ineligible(LayoutTranslationCloneOutcome $outcome, ?string $detail = null): self
    {
        return new self($outcome, null, $detail);
    }

    public static function failure(LayoutTranslationCloneOutcome $outcome, ?string $detail = null): self
    {
        return new self($outcome, null, $detail);
    }

    public function outcome(): LayoutTranslationCloneOutcome
    {
        return $this->outcome;
    }

    public function binding(): ?PersistedLayoutTranslationBinding
    {
        return $this->binding;
    }

    public function isCreated(): bool
    {
        return $this->outcome === LayoutTranslationCloneOutcome::Created;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'detail' => $this->detail,
            'binding' => $this->binding?->toArray(),
        ];
    }
}
