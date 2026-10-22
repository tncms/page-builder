<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Sections;

use Plugins\PageBuilder\Definitions\AbstractSectionDefinition;
use Plugins\PageBuilder\Schema\Fields\SelectField;
use Plugins\PageBuilder\Schema\Schema;
use Plugins\PageBuilder\Schema\Tab;
use Plugins\PageBuilder\Support\AccentPreset;
use Plugins\PageBuilder\Support\TonePreset;

/** A horizontal row of column blocks. */
class ColumnsSection extends AbstractSectionDefinition
{
    public function type(): string
    {
        return 'columns';
    }

    public function label(): string
    {
        return 'Columns';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-view-columns';
    }

    public function defaults(): array
    {
        return ['gap' => 'md'];
    }

    public function allowedBlocks(): array
    {
        return ['column'];
    }

    public function schema(): array
    {
        return Schema::make()->tabs([
            Tab::make('style')->label('Style')->fields([
                SelectField::make('gap')->label('Gap')
                    ->options(['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'])
                    ->default('md'),
                SelectField::make('variant')->label('Layout')
                    ->options(['' => 'Default', 'cards' => 'Cards', 'featured' => 'Featured + two', 'timeline' => 'Timeline'])
                    ->default(''),
                SelectField::make('accent')->label('Accent')
                    ->options(AccentPreset::options())
                    ->default(''),
                SelectField::make('tone')->label('Surface tone')
                    ->options(TonePreset::options())
                    ->default(''),
            ]),
        ])->toArray();
    }
}
