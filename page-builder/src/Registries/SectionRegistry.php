<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Registries;

/**
 * Registry of Page Builder section types.
 *
 * A section is a top-level layout container (row/band) that holds blocks. Empty
 * in Phase 1 — no built-in section types are registered yet; later phases and
 * third-party plugins populate it through the PageBuilder facade.
 */
class SectionRegistry extends AbstractRegistry
{
}
