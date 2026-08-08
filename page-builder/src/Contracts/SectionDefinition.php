<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Contracts;

/**
 * Marker contract for a section-level component definition (a top-level layout
 * container that holds blocks). See {@see ComponentDefinition}.
 */
interface SectionDefinition extends ComponentDefinition
{
    /**
     * Block types that may be placed inside this section (editor constraint).
     *
     * @return array<int, string>
     */
    public function allowedBlocks(): array;
}
