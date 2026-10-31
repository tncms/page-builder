<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Schema\Fields;

/** Plain HTML source entered by an author; output only through AuthoredHtml. */
class HtmlSourceField extends Field
{
    protected string $type = 'htmlsource';
}
