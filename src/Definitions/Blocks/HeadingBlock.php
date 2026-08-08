<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Blocks;

use Plugins\PageBuilder\Definitions\AbstractBlockDefinition;
use Plugins\PageBuilder\Schema\Fields\SelectField;
use Plugins\PageBuilder\Schema\Fields\TextField;
use Plugins\PageBuilder\Schema\Fields\ToggleField;
use Plugins\PageBuilder\Schema\Fields\TypographyField;
use Plugins\PageBuilder\Schema\Schema;
use Plugins\PageBuilder\Schema\Tab;

/** Heading block (h1–h6, whitelisted level). */
class HeadingBlock extends AbstractBlockDefinition
{
    public function type(): string
    {
        return 'heading';
    }

    public function label(): string
    {
        return 'Heading';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-h1';
    }

    public function defaults(): array
    {
        return ['text' => 'Heading', 'level' => 'h2'];
    }

    public function schema(): array
    {
        return Schema::make()->tabs([
            Tab::make('content')->label('Content')->fields([
                TextField::make('text')->label('Text')->required()->translatable(),
                SelectField::make('level')->label('Level')
                    ->options(['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6'])
                    ->default('h2'),
            ]),
            Tab::make('style')->label('Style')->fields([
                TypographyField::make('typography')->label('Typography'),
                ToggleField::make('uppercase')->label('Uppercase'),
            ]),
        ])->toArray();
    }
}
