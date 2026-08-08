<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Sections;

use Plugins\PageBuilder\Definitions\AbstractSectionDefinition;
use Plugins\PageBuilder\Schema\Fields\TextField;
use Plugins\PageBuilder\Schema\Schema;
use Plugins\PageBuilder\Schema\Tab;

/** Optional heading over a row of logo blocks. */
class LogoStripSection extends AbstractSectionDefinition
{
    public function type(): string
    {
        return 'logo-strip';
    }

    public function label(): string
    {
        return 'Logo Strip';
    }

    public function category(): string
    {
        return 'social-proof';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-squares-2x2';
    }

    public function defaults(): array
    {
        return ['heading' => 'Trusted by teams everywhere'];
    }

    public function allowedBlocks(): array
    {
        return ['logo'];
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
