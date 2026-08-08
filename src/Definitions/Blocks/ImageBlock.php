<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Blocks;

use Plugins\PageBuilder\Definitions\AbstractBlockDefinition;
use Plugins\PageBuilder\Schema\Fields\MediaField;
use Plugins\PageBuilder\Schema\Fields\TextField;
use Plugins\PageBuilder\Schema\Schema;

/** Image block with optional caption. */
class ImageBlock extends AbstractBlockDefinition
{
    public function type(): string
    {
        return 'image';
    }

    public function label(): string
    {
        return 'Image';
    }

    public function category(): string
    {
        return 'media';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-photo';
    }

    public function defaults(): array
    {
        return ['src' => '', 'alt' => '', 'caption' => ''];
    }

    public function schema(): array
    {
        return Schema::make()->fields([
            MediaField::make('src')->label('Image')->accept(['image/*']),
            TextField::make('alt')->label('Alt text')->translatable(),
            TextField::make('caption')->label('Caption')->translatable(),
        ])->toArray();
    }
}
