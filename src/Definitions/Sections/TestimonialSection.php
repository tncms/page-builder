<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Sections;

use Plugins\PageBuilder\Definitions\AbstractSectionDefinition;
use Plugins\PageBuilder\Schema\Fields\TextareaField;
use Plugins\PageBuilder\Schema\Fields\TextField;
use Plugins\PageBuilder\Schema\Schema;
use Plugins\PageBuilder\Schema\Tab;

/** A pull-quote band with its own quote/author/role, plus optional quote blocks. */
class TestimonialSection extends AbstractSectionDefinition
{
    public function type(): string
    {
        return 'testimonial';
    }

    public function label(): string
    {
        return 'Testimonial';
    }

    public function category(): string
    {
        return 'social-proof';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public function defaults(): array
    {
        return [
            'quote' => 'This product changed how our team works.',
            'author' => 'Jane Doe',
            'role' => 'Product Lead',
        ];
    }

    public function allowedBlocks(): array
    {
        return ['quote'];
    }

    public function schema(): array
    {
        return Schema::make()->tabs([
            Tab::make('content')->label('Content')->fields([
                TextareaField::make('quote')->label('Quote')->translatable(),
                TextField::make('author')->label('Author')->translatable(),
                TextField::make('role')->label('Author role')->translatable(),
            ]),
        ])->toArray();
    }
}
