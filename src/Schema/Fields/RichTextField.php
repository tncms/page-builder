<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Schema\Fields;

/** Sanitized rich-text (limited HTML) field. */
class RichTextField extends Field
{
    protected string $type = 'richtext';
}
