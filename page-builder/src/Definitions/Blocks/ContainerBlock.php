<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Blocks;

use Plugins\PageBuilder\Definitions\AbstractBlockDefinition;
use Plugins\PageBuilder\Schema\Fields\ColorField;
use Plugins\PageBuilder\Schema\Fields\SelectField;
use Plugins\PageBuilder\Schema\Schema;
use Plugins\PageBuilder\Schema\Tab;

/**
 * A first-class layout node (Phase 25.3). A Container lives inside a Section, holds
 * Columns, and controls the row's layout, width, spacing and alignment. It is the
 * neutral composition wrapper — Section → Container → Column → Elements — so the
 * builder never needs special-case Row/Grid section types.
 *
 * Nested children live in `data.blocks` (the same convention {@see ColumnBlock}
 * uses), rendered recursively by the renderer.
 */
class ContainerBlock extends AbstractBlockDefinition
{
    public function type(): string
    {
        return 'container';
    }

    public function label(): string
    {
        return 'Container';
    }

    public function category(): string
    {
        return 'layout';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-square-3-stack-3d';
    }

    public function defaults(): array
    {
        return [
            'layout' => 'flex',
            'direction' => 'row',
            'width' => 'normal',
            'gap' => 'md',
            'padding' => 'none',
            'align' => 'stretch',
            'justify' => 'start',
            'background' => '',
            'blocks' => [],
        ];
    }

    /** A Container accepts Columns (and only Columns). */
    public function supports(): array
    {
        return ['blocks' => ['column']];
    }

    public function schema(): array
    {
        return Schema::make()->tabs([
            Tab::make('content')->label('Content')->fields([
                SelectField::make('layout')->label('Layout')
                    ->options(['flex' => 'Flex (row)', 'grid' => 'Grid', 'block' => 'Stacked'])
                    ->default('flex'),
                SelectField::make('direction')->label('Direction')
                    ->options(['row' => 'Row (horizontal)', 'column' => 'Column (vertical)'])
                    ->default('row'),
                SelectField::make('width')->label('Width')
                    ->options(['normal' => 'Normal (boxed)', 'wide' => 'Wide', 'full' => 'Full width'])
                    ->default('normal'),
            ]),
            Tab::make('style')->label('Style')->fields([
                SelectField::make('gap')->label('Gap')
                    ->options(['none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'])
                    ->default('md'),
                SelectField::make('align')->label('Align items')
                    ->options(['start' => 'Start', 'center' => 'Center', 'end' => 'End', 'stretch' => 'Stretch'])
                    ->default('stretch'),
                SelectField::make('justify')->label('Justify content')
                    ->options(['start' => 'Start', 'center' => 'Center', 'end' => 'End', 'between' => 'Space between', 'around' => 'Space around'])
                    ->default('start'),
                ColorField::make('background')->label('Background'),
                SelectField::make('padding')->label('Padding')
                    ->options(['none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'])
                    ->default('none'),
            ]),
        ])->toArray();
    }
}
