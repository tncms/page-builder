<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Ownership;

use InvalidArgumentException;

/**
 * A generic, immutable Layout Owner value object (LT-3, Option D).
 *
 * Layer: Page Builder core (Ownership). Used for plain `(owner_type, owner_id)`
 * owners such as the legacy `page`/`post` content owners. Translation-agnostic.
 */
final class LayoutOwner implements LayoutOwnerInterface
{
    public function __construct(
        private readonly string $ownerType,
        private readonly int|string $ownerId,
    ) {
        if (trim($ownerType) === '' || trim((string) $ownerId) === '') {
            throw new InvalidArgumentException('A layout owner requires a non-empty owner type and id.');
        }
    }

    public function ownerType(): string
    {
        return $this->ownerType;
    }

    public function ownerId(): int|string
    {
        return $this->ownerId;
    }
}
