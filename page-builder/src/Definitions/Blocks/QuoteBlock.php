<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Blocks;

use Plugins\PageBuilder\Definitions\AbstractBlockDefinition;
use Plugins\PageBuilder\Schema\Fields\TextareaField;
use Plugins\PageBuilder\Schema\Fields\TextField;
use Plugins\PageBuilder\Schema\Schema;

/** A pull-quote / testimonial with optional author + role. */
class QuoteBlock extends AbstractBlockDefinition
{
    public function type(): string
    {
        return 'quote';
    }

    public function label(): string
    {
        return 'Quote';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-chat-bubble-bottom-center-text';
    }

    public function defaults(): array
    {
        return ['text' => 'Add a memorable quote here.', 'author' => 'Author', 'role' => ''];
    }

    public function schema(): array
    {
        return Schema::make()->fields([
            TextareaField::make('text')->label('Quote')->required()->translatable(),
            TextField::make('author')->label('Author')->translatable(),
            TextField::make('role')->label('Author role')->translatable(),
        ])->toArray();
    }
}
