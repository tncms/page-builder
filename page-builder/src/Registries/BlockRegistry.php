<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Registries;

/**
 * Registry of Page Builder block types.
 *
 * A block is an atomic piece of content (text, image, button, …) placed inside
 * a section. Empty in Phase 1 — no built-in block types are registered yet;
 * later phases and third-party plugins populate it through the PageBuilder
 * facade.
 */
class BlockRegistry extends AbstractRegistry
{
}
