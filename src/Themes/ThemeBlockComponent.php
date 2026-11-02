<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Themes;

use Plugins\PageBuilder\Contracts\BlockDefinition;

/** A theme-declared block (PB-XREG-4). See {@see ThemeComponentDefinition}. */
final class ThemeBlockComponent extends ThemeComponentDefinition implements BlockDefinition {}
