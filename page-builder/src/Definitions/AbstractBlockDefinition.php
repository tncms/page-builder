<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions;

use Plugins\PageBuilder\Contracts\BlockDefinition;

/**
 * Base class for block definitions. Defaults the category to "basic".
 */
abstract class AbstractBlockDefinition extends AbstractComponentDefinition implements BlockDefinition
{
    public function category(): string
    {
        return 'basic';
    }
}
