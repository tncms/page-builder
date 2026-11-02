<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Themes;

use Plugins\PageBuilder\Contracts\BlockDefinition;
use Plugins\PageBuilder\Interactive\Contracts\InteractiveComponentDefinition;
use Plugins\PageBuilder\Interactive\InteractiveBehavior;

/** A theme-declared interactive block (PB-XREG-4); its behavior is validated by the PB-XREG-3 rules. */
final class ThemeInteractiveBlockComponent extends ThemeComponentDefinition implements BlockDefinition, InteractiveComponentDefinition
{
    public function behavior(): InteractiveBehavior
    {
        return $this->declaredBehavior();
    }
}
