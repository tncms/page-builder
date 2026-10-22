<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Sections;

use Plugins\PageBuilder\Definitions\AbstractSectionDefinition;
use Plugins\PageBuilder\Schema\Fields\ColorField;
use Plugins\PageBuilder\Schema\Fields\SelectField;
use Plugins\PageBuilder\Schema\Schema;
use Plugins\PageBuilder\Schema\Tab;
use Plugins\PageBuilder\Support\AccentPreset;
use Plugins\PageBuilder\Support\TonePreset;

/**
 * A neutral, general-purpose section (Phase 25.3) — the top of the composition tree
 * Section → Container → Column → Elements. It deliberately carries no semantic
 * meaning (unlike Hero/CTA/FAQ) so it can hold Containers (or plain elements for
 * simple content). This is the "use Section → Container → Column" answer to the
 * spec's "do NOT create Column/Row/Grid sections" rule.
 */
class GenericSection extends AbstractSectionDefinition
{
    public function type(): string
    {
        return 'section';
    }

    public function label(): string
    {
        return 'Section';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-square-2-stack';
    }

    public function defaults(): array
    {
        return ['width' => 'normal', 'padding' => 'md', 'background' => ''];
    }

    /** Holds Containers (composition) plus plain elements for simple content. */
    public function allowedBlocks(): array
    {
        return ['container', 'heading', 'text', 'button', 'image', 'icon', 'quote'];
    }

    public function schema(): array
    {
        return Schema::make()->tabs([
            Tab::make('style')->label('Style')->fields([
                SelectField::make('width')->label('Width')
                    ->options(['normal' => 'Normal (boxed)', 'wide' => 'Wide', 'full' => 'Full width'])
                    ->default('normal'),
                SelectField::make('padding')->label('Padding')
                    ->options(['none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'])
                    ->default('md'),
                SelectField::make('accent')->label('Accent')
                    ->options(AccentPreset::options())
                    ->default(''),
                SelectField::make('tone')->label('Surface tone')
                    ->options(TonePreset::options())
                    ->default(''),
                ColorField::make('background')->label('Background'),
            ]),
        ])->toArray();
    }
}
