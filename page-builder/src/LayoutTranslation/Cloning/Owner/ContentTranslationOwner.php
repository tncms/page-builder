<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Owner;

use InvalidArgumentException;
use Plugins\PageBuilder\Ownership\LayoutOwnerInterface;

/**
 * The Translation implementation of a generic Layout Owner (LT-3, Option D).
 *
 * Layer: Translation (LayoutTranslation). It maps a page translation to a
 * `pb_layouts` storage attachment via `owner_type = content_translation`,
 * `owner_id = ContentTranslation.id`. This is NOT a Translation feature inside
 * Page Builder — it is one registered owner. The dependency points Translation →
 * Page Builder (it implements the core {@see LayoutOwnerInterface}); Page Builder
 * never depends on this class.
 */
final class ContentTranslationOwner implements LayoutOwnerInterface
{
    public const OWNER_TYPE = 'content_translation';

    public function __construct(private readonly int $contentTranslationId)
    {
        if ($contentTranslationId <= 0) {
            throw new InvalidArgumentException('A content translation owner requires a positive ContentTranslation id.');
        }
    }

    public function ownerType(): string
    {
        return self::OWNER_TYPE;
    }

    public function ownerId(): int
    {
        return $this->contentTranslationId;
    }
}
