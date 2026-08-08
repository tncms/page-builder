<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Sections;

use Plugins\PageBuilder\Definitions\AbstractSectionDefinition;
use Plugins\PageBuilder\Schema\Fields\SelectField;
use Plugins\PageBuilder\Schema\Fields\TextareaField;
use Plugins\PageBuilder\Schema\Schema;
use Plugins\PageBuilder\Schema\Tab;

/** Prose band with width + alignment, holding heading/text/button blocks. */
class TextSection extends AbstractSectionDefinition
{
    public function type(): string
    {
        return 'text-section';
    }

    public function label(): string
    {
        return 'Text Section';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-bars-3-bottom-left';
    }

    public function defaults(): array
    {
        return ['width' => 'normal', 'align' => 'left', 'content' => 'Add your text here.'];
    }

    public function allowedBlocks(): array
    {
        return ['heading', 'text', 'button'];
    }

    public function schema(): array
    {
        return Schema::make()->tabs([
            Tab::make('content')->label('Content')->fields([
                TextareaField::make('content')->label('Content')->translatable(),
            ]),
            Tab::make('style')->label('Style')->fields([
                SelectField::make('width')->label('Width')
                    ->options(['narrow' => 'Narrow', 'normal' => 'Normal', 'wide' => 'Wide'])
                    ->default('normal'),
                SelectField::make('align')->label('Alignment')
                    ->options(['left' => 'Left', 'center' => 'Center', 'right' => 'Right'])
                    ->default('left'),
            ]),
        ])->toArray();
    }
}
