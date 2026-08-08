<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Schema\Fields;

use Plugins\PageBuilder\Support\TypographyValue;

/** Typography field (family/size/weight/line-height/…). */
class TypographyField extends Field
{
    protected string $type = 'typography';

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->default = TypographyValue::make()->toArray();
    }
}
