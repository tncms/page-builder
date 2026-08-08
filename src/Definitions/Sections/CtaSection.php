<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Sections;

use Plugins\PageBuilder\Definitions\AbstractSectionDefinition;
use Plugins\PageBuilder\Schema\Fields\ColorField;
use Plugins\PageBuilder\Schema\Fields\SelectField;
use Plugins\PageBuilder\Schema\Fields\TextareaField;
use Plugins\PageBuilder\Schema\Fields\TextField;
use Plugins\PageBuilder\Schema\Fields\UrlField;
use Plugins\PageBuilder\Schema\Schema;
use Plugins\PageBuilder\Schema\Tab;

/** Focused call-to-action band holding heading/text/button blocks. */
class CtaSection extends AbstractSectionDefinition
{
    public function type(): string
    {
        return 'cta';
    }

    public function label(): string
    {
        return 'Call to Action';
    }

    public function category(): string
    {
        return 'marketing';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-megaphone';
    }

    public function defaults(): array
    {
        return [
            'align' => 'center',
            'title' => 'Ready to get started?',
            'text' => 'A short, persuasive line that prompts the next step.',
            'button_label' => 'Get started',
            'button_url' => '#',
        ];
    }

    public function allowedBlocks(): array
    {
        return ['heading', 'text', 'button'];
    }

    public function schema(): array
    {
        return Schema::make()->tabs([
            Tab::make('content')->label('Content')->fields([
                TextField::make('title')->label('Title')->translatable(),
                TextareaField::make('text')->label('Text')->translatable(),
                TextField::make('button_label')->label('Button label')->translatable(),
                UrlField::make('button_url')->label('Button URL'),
            ]),
            Tab::make('style')->label('Style')->fields([
                SelectField::make('align')->label('Alignment')
                    ->options(['left' => 'Left', 'center' => 'Center', 'right' => 'Right'])
                    ->default('center'),
                ColorField::make('background')->label('Background')->token('color.primary'),
            ]),
        ])->toArray();
    }
}
