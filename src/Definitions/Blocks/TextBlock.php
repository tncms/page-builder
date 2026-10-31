<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Blocks;

use Plugins\PageBuilder\Definitions\AbstractBlockDefinition;
use Plugins\PageBuilder\Schema\Fields\RichTextField;
use Plugins\PageBuilder\Schema\Fields\SelectField;
use Plugins\PageBuilder\Schema\Schema;

/**
 * Text block. The persisted type and its legacy plain `text` value are unchanged
 * (escaped on output). The Inspector edits it with the visual editor, which writes
 * rich HTML to the translatable `content` key for the edited locale only
 * (PB-TEXT-AUTHORING-R1, see {@see \Plugins\PageBuilder\Support\TextBlockContent}).
 */
class TextBlock extends AbstractBlockDefinition
{
    public function type(): string
    {
        return 'text';
    }

    public function label(): string
    {
        return 'Text';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-bars-3-bottom-left';
    }

    public function defaults(): array
    {
        return ['text' => 'Add your text here.'];
    }

    public function schema(): array
    {
        return Schema::make()->fields([
            RichTextField::make('content')->label('Text')->translatable()
                ->editor(['legacyField' => 'text', 'maxBytes' => \Plugins\PageBuilder\Support\AuthoredHtml::MAX_BYTES]),
            SelectField::make('variant')->label('Style')
                ->options(['default' => 'Default', 'eyebrow' => 'Eyebrow', 'lead' => 'Lead'])
                ->default('default'),
        ])->toArray();
    }
}
