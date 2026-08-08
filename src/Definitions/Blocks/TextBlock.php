<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Blocks;

use Plugins\PageBuilder\Definitions\AbstractBlockDefinition;
use Plugins\PageBuilder\Schema\Fields\TextareaField;
use Plugins\PageBuilder\Schema\Schema;

/** Escaped plain-text paragraph block. */
class TextBlock extends AbstractBlockDefinition
{
    public function type(): string
    {
        return 'text';
    }

    public function label(): string
    {
        return 'Text';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-bars-3-bottom-left';
    }

    public function defaults(): array
    {
        return ['text' => 'Add your text here.'];
    }

    public function schema(): array
    {
        return Schema::make()->fields([
            TextareaField::make('text')->label('Text')->translatable(),
        ])->toArray();
    }
}
