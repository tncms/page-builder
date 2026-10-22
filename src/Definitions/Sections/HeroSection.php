<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Sections;

use Plugins\PageBuilder\Definitions\AbstractSectionDefinition;
use Plugins\PageBuilder\Schema\Fields\ColorField;
use Plugins\PageBuilder\Schema\Fields\SelectField;
use Plugins\PageBuilder\Schema\Fields\SpacingField;
use Plugins\PageBuilder\Schema\Fields\TextareaField;
use Plugins\PageBuilder\Schema\Fields\TextField;
use Plugins\PageBuilder\Schema\Fields\UrlField;
use Plugins\PageBuilder\Schema\Group;
use Plugins\PageBuilder\Schema\Schema;
use Plugins\PageBuilder\Schema\Tab;
use Plugins\PageBuilder\Support\AccentPreset;

/** Full-width intro band holding heading/text/button/image blocks. */
class HeroSection extends AbstractSectionDefinition
{
    public function type(): string
    {
        return 'hero';
    }

    public function label(): string
    {
        return 'Hero';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-rectangle-group';
    }

    public function defaults(): array
    {
        return [
            'align' => 'center',
            'style' => 'plain',
            'width' => 'normal',
            'title' => 'Your headline here',
            'subtitle' => 'Add a short supporting subtitle to introduce your page.',
            'button_label' => 'Get started',
            'button_url' => '#',
        ];
    }

    public function allowedBlocks(): array
    {
        return ['heading', 'text', 'button', 'image'];
    }

    public function schema(): array
    {
        return Schema::make()->tabs([
            Tab::make('content')->label('Content')->fields([
                TextField::make('eyebrow')->label('Eyebrow')->translatable()
                    ->help('Optional short kicker shown above the title.'),
                TextField::make('title')->label('Title')->translatable(),
                TextareaField::make('subtitle')->label('Subtitle')->translatable(),
                TextField::make('button_label')->label('Button label')->translatable(),
                UrlField::make('button_url')->label('Button URL'),
                UrlField::make('image_url')->label('Media image URL')
                    ->help('Shown beside the content when the Split layout is selected.'),
                TextField::make('image_alt')->label('Media alt text')->translatable(),
            ]),
            Tab::make('style')->label('Style')->fields([
                SelectField::make('style')->label('Appearance')
                    ->options(['plain' => 'Plain', 'surface' => 'Surface', 'dark' => 'Dark'])
                    ->default('plain'),
                SelectField::make('layout')->label('Layout')
                    ->options(['stacked' => 'Stacked', 'split' => 'Split (media beside content)'])
                    ->default('stacked'),
                SelectField::make('media_position')->label('Media position')
                    ->options(['end' => 'After content', 'start' => 'Before content'])
                    ->default('end'),
                SelectField::make('media_shape')->label('Media shape')
                    ->options(['plain' => 'Plain', 'rounded' => 'Rounded', 'shadow' => 'Rounded + shadow'])
                    ->default('rounded'),
                SelectField::make('align')->label('Alignment')
                    ->options(['left' => 'Left', 'center' => 'Center', 'right' => 'Right'])
                    ->default('center'),
                SelectField::make('width')->label('Content width')
                    ->options(['narrow' => 'Narrow', 'normal' => 'Normal', 'wide' => 'Wide', 'full' => 'Full'])
                    ->default('normal'),
                SelectField::make('accent')->label('Accent')
                    ->options(AccentPreset::options())
                    ->default(''),
                ColorField::make('background')->label('Background')->token('color.background'),
            ]),
            Tab::make('advanced')->label('Advanced')->fields([
                // Box-model spacing, authored per breakpoint (PB-PROD-1). The dotted
                // field names land the values at `data.spacing.{padding,margin}` —
                // the box-model path the runtime renders ({@see \Plugins\PageBuilder\Support\SpacingStyle}
                // / {@see \Plugins\PageBuilder\Support\ResponsiveSpacingStyle}). `->responsive()`
                // turns each into a desktop/tablet/mobile control via the shared engine.
                Group::make('spacing')->label('Spacing')->fields([
                    SpacingField::make('spacing.padding')->label('Padding')->responsive(),
                    SpacingField::make('spacing.margin')->label('Margin')->responsive(),
                ]),
                TextField::make('anchor_id')->label('Anchor ID')
                    ->help('Adds an id for on-page links.'),
                TextField::make('custom_class')->label('CSS class')
                    ->help('Extra class names for theming.'),
            ]),
        ])->toArray();
    }

    public function editor(): array
    {
        return [
            'presets' => [
                ['name' => 'centered', 'label' => 'Centered', 'data' => ['align' => 'center', 'style' => 'plain', 'width' => 'normal']],
                ['name' => 'minimal', 'label' => 'Minimal', 'data' => ['align' => 'left', 'style' => 'plain', 'width' => 'narrow']],
                ['name' => 'surface', 'label' => 'Surface', 'data' => ['align' => 'center', 'style' => 'surface', 'width' => 'normal']],
                ['name' => 'dark-cta', 'label' => 'Dark CTA', 'data' => ['align' => 'center', 'style' => 'dark', 'width' => 'normal']],
                ['name' => 'split-media', 'label' => 'Split media', 'data' => ['align' => 'left', 'style' => 'plain', 'width' => 'wide', 'layout' => 'split']],
            ],
        ];
    }
}
