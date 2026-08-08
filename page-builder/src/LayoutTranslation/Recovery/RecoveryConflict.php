<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

/**
 * One explicit recovery conflict (LT-9D).
 *
 * Layer: Translation (recovery / conflict). A single detected divergence between what the recovery assumes
 * and current reality — a typed {@see RecoveryConflictType} plus a non-sensitive detail. Every conflict is
 * explicit; nothing is inferred. It carries no document content.
 */
final class RecoveryConflict
{
    private function __construct(
        public readonly RecoveryConflictType $type,
        public readonly string $detail,
    ) {}

    public static function of(RecoveryConflictType $type, string $detail = ''): self
    {
        return new self($type, $detail);
    }

    public function isBlocking(): bool
    {
        return $this->type->isBlocking();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'blocking' => $this->type->isBlocking(),
            'detail' => $this->detail,
        ];
    }
}
