<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Blocks;

use Plugins\PageBuilder\Definitions\AbstractBlockDefinition;
use Plugins\PageBuilder\Schema\Fields\TextareaField;
use Plugins\PageBuilder\Schema\Fields\TextField;
use Plugins\PageBuilder\Schema\Schema;

/** A single question/answer disclosure (native <details>). */
class AccordionItemBlock extends AbstractBlockDefinition
{
    public function type(): string
    {
        return 'accordion-item';
    }

    public function label(): string
    {
        return 'Accordion Item';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-chevron-down';
    }

    public function defaults(): array
    {
        return ['question' => 'Question?', 'answer' => 'Answer goes here.'];
    }

    public function schema(): array
    {
        return Schema::make()->fields([
            TextField::make('question')->label('Question')->required()->translatable(),
            TextareaField::make('answer')->label('Answer')->translatable(),
        ])->toArray();
    }
}
