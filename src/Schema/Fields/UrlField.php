<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Schema\Fields;

/** URL field (values are scheme-sanitised at render time by Html::safeUrl). */
class UrlField extends Field
{
    protected string $type = 'url';
}
