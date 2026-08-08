<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Schema\Fields;

use Plugins\PageBuilder\Support\SpacingValue;

/** Box-spacing (top/right/bottom/left) field, e.g. padding / margin. */
class SpacingField extends Field
{
    protected string $type = 'spacing';

    protected mixed $default = null;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->default = SpacingValue::make()->toArray();
    }
}
