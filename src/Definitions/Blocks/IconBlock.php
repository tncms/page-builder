<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Blocks;

use Plugins\PageBuilder\Definitions\AbstractBlockDefinition;
use Plugins\PageBuilder\Schema\Fields\SelectField;
use Plugins\PageBuilder\Schema\Fields\TextField;
use Plugins\PageBuilder\Schema\Schema;
use Plugins\PageBuilder\Support\IconLibrary;

/** Icon block (scoped by name; optional accessible label). */
class IconBlock extends AbstractBlockDefinition
{
    public function type(): string
    {
        return 'icon';
    }

    public function label(): string
    {
        return 'Icon';
    }

    public function category(): string
    {
        return 'media';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-sparkles';
    }

    public function defaults(): array
    {
        return ['name' => '', 'label' => ''];
    }

    public function schema(): array
    {
        return Schema::make()->fields([
            SelectField::make('name')->label('Icon')
                ->options(IconLibrary::options())
                ->default(''),
            TextField::make('label')->label('Accessible label')->translatable(),
        ])->toArray();
    }
}
