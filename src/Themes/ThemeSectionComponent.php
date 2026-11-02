<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Themes;

use Plugins\PageBuilder\Contracts\SectionDefinition;

/** A theme-declared section (PB-XREG-4). See {@see ThemeComponentDefinition}. */
final class ThemeSectionComponent extends ThemeComponentDefinition implements SectionDefinition {}
