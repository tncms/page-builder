<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Schema\Fields;

/** Single- or multi-choice select field. */
class SelectField extends Field
{
    protected string $type = 'select';

    public function multiple(bool $multiple = true): static
    {
        return $this->editor(['multiple' => $multiple]);
    }
}
