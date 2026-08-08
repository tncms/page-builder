<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions;

use Plugins\PageBuilder\Contracts\SectionDefinition;

/**
 * Base class for section definitions. Defaults the category to "layout" and
 * exposes the allowed child block types through `supports()['blocks']`.
 */
abstract class AbstractSectionDefinition extends AbstractComponentDefinition implements SectionDefinition
{
    public function category(): string
    {
        return 'layout';
    }

    public function allowedBlocks(): array
    {
        return [];
    }

    public function supports(): array
    {
        return ['blocks' => $this->allowedBlocks()];
    }
}
