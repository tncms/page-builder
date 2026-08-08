<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Blocks;

use Plugins\PageBuilder\Definitions\AbstractBlockDefinition;
use Plugins\PageBuilder\Schema\Fields\ColorField;
use Plugins\PageBuilder\Schema\Fields\SelectField;
use Plugins\PageBuilder\Schema\Schema;
use Plugins\PageBuilder\Schema\Tab;

/** A container block (inside a Container / columns section) that holds nested blocks. */
class ColumnBlock extends AbstractBlockDefinition
{
    public function type(): string
    {
        return 'column';
    }

    public function label(): string
    {
        return 'Column';
    }

    public function category(): string
    {
        return 'layout';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-view-columns';
    }

    public function defaults(): array
    {
        return ['width' => 'auto', 'valign' => 'start', 'halign' => 'stretch', 'blocks' => []];
    }

    /** Column is itself a container: it advertises its allowed child blocks. */
    public function supports(): array
    {
        return ['blocks' => ['heading', 'text', 'button', 'image', 'icon', 'quote']];
    }

    public function schema(): array
    {
        return Schema::make()->tabs([
            Tab::make('content')->label('Content')->fields([
                // Percentage widths (Phase 25.3). Legacy semantic values
                // (auto/half/third/quarter) still render — see column.blade.php.
                SelectField::make('width')->label('Width')
                    ->options([
                        'auto' => 'Auto',
                        '100' => '100%',
                        '75' => '75%',
                        '66' => '66%',
                        '50' => '50%',
                        '33' => '33%',
                        '25' => '25%',
                    ])
                    ->default('auto'),
            ]),
            Tab::make('style')->label('Style')->fields([
                SelectField::make('valign')->label('Vertical align')
                    ->options(['start' => 'Top', 'center' => 'Middle', 'end' => 'Bottom'])
                    ->default('start'),
                SelectField::make('halign')->label('Horizontal align')
                    ->options(['stretch' => 'Stretch', 'start' => 'Left', 'center' => 'Center', 'end' => 'Right'])
                    ->default('stretch'),
                ColorField::make('background')->label('Background'),
                SelectField::make('padding')->label('Padding')
                    ->options(['none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'])
                    ->default('none'),
            ]),
        ])->toArray();
    }
}
