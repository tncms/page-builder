<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts;

/**
 * Reports whether a referenced pb_layouts document row exists — LT-2.
 *
 * Layer: Persistence / Contract. A narrow seam so binding integrity validation can
 * detect a missing referenced layout without depending on the ORM directly
 * (keeping it unit-testable). It reads the canonical layout-document store
 * (pb_layouts); it never writes.
 */
interface LayoutDocumentLocatorInterface
{
    public function exists(int $layoutId): bool;
}
