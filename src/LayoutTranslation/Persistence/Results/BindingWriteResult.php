<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Persistence\Results;

/**
 * The immutable, typed result of a binding write — LT-2.
 *
 * Layer: Persistence / Result. A write never returns a model or raw row; it
 * returns this value object carrying a typed {@see BindingWriteOutcome} and,
 * when applicable, the resulting domain binding. This is an LT-2 type and does
 * not touch the frozen LT-1 descriptor.
 */
final class BindingWriteResult
{
    private function __construct(
        public readonly BindingWriteOutcome $outcome,
        public readonly ?PersistedLayoutTranslationBinding $binding,
        public readonly ?string $detail,
    ) {}

    public static function created(PersistedLayoutTranslationBinding $binding): self
    {
        return new self(BindingWriteOutcome::Created, $binding, null);
    }

    public static function alreadyExists(PersistedLayoutTranslationBinding $binding): self
    {
        return new self(BindingWriteOutcome::AlreadyExists, $binding, null);
    }

    public static function updated(PersistedLayoutTranslationBinding $binding): self
    {
        return new self(BindingWriteOutcome::Updated, $binding, null);
    }

    public static function conflict(string $detail): self
    {
        return new self(BindingWriteOutcome::Conflict, null, $detail);
    }

    public static function invalidIdentity(string $detail): self
    {
        return new self(BindingWriteOutcome::InvalidIdentity, null, $detail);
    }

    public static function invalidReference(string $detail): self
    {
        return new self(BindingWriteOutcome::InvalidReference, null, $detail);
    }

    public static function failed(string $detail): self
    {
        return new self(BindingWriteOutcome::Failed, null, $detail);
    }

    public function outcome(): BindingWriteOutcome
    {
        return $this->outcome;
    }

    public function binding(): ?PersistedLayoutTranslationBinding
    {
        return $this->binding;
    }

    public function isSuccessful(): bool
    {
        return in_array($this->outcome, [
            BindingWriteOutcome::Created,
            BindingWriteOutcome::AlreadyExists,
            BindingWriteOutcome::Updated,
        ], true);
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
