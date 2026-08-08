<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Sections;

use Plugins\PageBuilder\Definitions\AbstractSectionDefinition;
use Plugins\PageBuilder\Schema\Fields\TextField;
use Plugins\PageBuilder\Schema\Schema;
use Plugins\PageBuilder\Schema\Tab;

/** Optional heading over a list of accordion-item blocks. */
class FaqSection extends AbstractSectionDefinition
{
    public function type(): string
    {
        return 'faq';
    }

    public function label(): string
    {
        return 'FAQ';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-question-mark-circle';
    }

    public function defaults(): array
    {
        return ['heading' => 'Frequently asked questions'];
    }

    public function allowedBlocks(): array
    {
        return ['accordion-item'];
    }

    public function schema(): array
    {
        return Schema::make()->tabs([
            Tab::make('content')->label('Content')->fields([
                TextField::make('heading')->label('Heading'),
            ]),
        ])->toArray();
    }
}
