<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Schema\Fields;

/** Boolean on/off field. */
class ToggleField extends Field
{
    protected string $type = 'toggle';

    protected mixed $default = false;
}
