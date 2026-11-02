<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Themes;

use Plugins\PageBuilder\Contracts\SectionDefinition;
use Plugins\PageBuilder\Interactive\Contracts\InteractiveComponentDefinition;
use Plugins\PageBuilder\Interactive\InteractiveBehavior;

/** A theme-declared interactive section (PB-XREG-4); its behavior is validated by the PB-XREG-3 rules. */
final class ThemeInteractiveSectionComponent extends ThemeComponentDefinition implements InteractiveComponentDefinition, SectionDefinition
{
    public function behavior(): InteractiveBehavior
    {
        return $this->declaredBehavior();
    }
}
