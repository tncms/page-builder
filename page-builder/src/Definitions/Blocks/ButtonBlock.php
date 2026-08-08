<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Blocks;

use Plugins\PageBuilder\Definitions\AbstractBlockDefinition;
use Plugins\PageBuilder\Schema\Fields\SelectField;
use Plugins\PageBuilder\Schema\Fields\TextField;
use Plugins\PageBuilder\Schema\Fields\UrlField;
use Plugins\PageBuilder\Schema\Schema;
use Plugins\PageBuilder\Schema\Tab;

/** Link button block (href scheme-sanitised at render). */
class ButtonBlock extends AbstractBlockDefinition
{
    public function type(): string
    {
        return 'button';
    }

    public function label(): string
    {
        return 'Button';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-cursor-arrow-rays';
    }

    public function defaults(): array
    {
        return ['label' => 'Button', 'url' => '#', 'style' => 'primary', 'size' => 'md'];
    }

    public function schema(): array
    {
        return Schema::make()->tabs([
            Tab::make('content')->label('Content')->fields([
                TextField::make('label')->label('Label')->required()->translatable(),
                UrlField::make('url')->label('URL'),
            ]),
            Tab::make('style')->label('Style')->fields([
                SelectField::make('style')->label('Variant')
                    ->options([
                        'primary' => 'Primary',
                        'secondary' => 'Secondary',
                        'outline' => 'Outline',
                        'ghost' => 'Ghost',
                        'link' => 'Link',
                    ])
                    ->default('primary'),
                SelectField::make('size')->label('Size')
                    ->options(['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'])
                    ->default('md'),
            ]),
            Tab::make('advanced')->label('Advanced')->fields([
                TextField::make('custom_class')->label('CSS class')
                    ->help('Extra class names for theming.'),
            ]),
        ])->toArray();
    }

    public function editor(): array
    {
        return [
            'presets' => [
                ['name' => 'primary', 'label' => 'Primary', 'data' => ['style' => 'primary', 'size' => 'md']],
                ['name' => 'outline', 'label' => 'Outline', 'data' => ['style' => 'outline', 'size' => 'md']],
                ['name' => 'ghost', 'label' => 'Ghost', 'data' => ['style' => 'ghost', 'size' => 'md']],
            ],
        ];
    }
}
