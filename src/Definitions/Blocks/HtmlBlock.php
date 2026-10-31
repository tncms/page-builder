<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Blocks;

use Plugins\PageBuilder\Definitions\AbstractBlockDefinition;
use Plugins\PageBuilder\Schema\Fields\HtmlSourceField;
use Plugins\PageBuilder\Schema\Schema;
use Plugins\PageBuilder\Support\AuthoredHtml;

/**
 * Custom HTML block (PB-TEXT-AUTHORING-R1). Stores the author's HTML source
 * verbatim (per locale) and renders it only through
 * {@see AuthoredHtml::render()} with the CUSTOM profile: Core's sanitizer, then
 * Page Builder's narrowing. Not a script or embed block: unsafe markup is removed.
 */
class HtmlBlock extends AbstractBlockDefinition
{
    public function type(): string
    {
        return 'html';
    }

    public function label(): string
    {
        return 'Custom HTML';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-code-bracket';
    }

    public function defaults(): array
    {
        return ['html' => ''];
    }

    public function schema(): array
    {
        return Schema::make()->fields([
            HtmlSourceField::make('html')->label('HTML source')->translatable()
                ->editor(['maxBytes' => AuthoredHtml::MAX_BYTES, 'maxDepth' => AuthoredHtml::MAX_DEPTH]),
        ])->toArray();
    }
}
