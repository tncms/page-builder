<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Sections;

use Plugins\PageBuilder\Definitions\AbstractSectionDefinition;
use Plugins\PageBuilder\Schema\Fields\MediaField;
use Plugins\PageBuilder\Schema\Fields\SelectField;
use Plugins\PageBuilder\Schema\Fields\TextField;
use Plugins\PageBuilder\Schema\Schema;
use Plugins\PageBuilder\Schema\Tab;

/** Media band wrapping one or more image blocks. */
class ImageSection extends AbstractSectionDefinition
{
    public function type(): string
    {
        return 'image-section';
    }

    public function label(): string
    {
        // Phase 26.3.14 — "Image Section" (not bare "Image"), matching the
        // "Text Section" convention. A bare "Image" collided with the Image
        // *block* in the Elements palette, so users dragged this section into a
        // Column where a section can't nest — it snapped to the root ("fell
        // outside the column"). Distinct labels remove that ambiguity.
        return 'Image Section';
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
        return ['align' => 'center', 'image_url' => '', 'alt' => '', 'caption' => ''];
    }

    public function allowedBlocks(): array
    {
        return ['image'];
    }

    public function schema(): array
    {
        return Schema::make()->tabs([
            Tab::make('content')->label('Content')->fields([
                MediaField::make('image_url')->label('Image')->accept(['image/*']),
                TextField::make('alt')->label('Alt text')->translatable(),
                TextField::make('caption')->label('Caption')->translatable(),
            ]),
            Tab::make('style')->label('Style')->fields([
                SelectField::make('align')->label('Alignment')
                    ->options(['left' => 'Left', 'center' => 'Center', 'right' => 'Right'])
                    ->default('center'),
            ]),
        ])->toArray();
    }
}
