<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions\Blocks;

use Plugins\PageBuilder\Definitions\AbstractBlockDefinition;
use Plugins\PageBuilder\Schema\Fields\MediaField;
use Plugins\PageBuilder\Schema\Fields\TextField;
use Plugins\PageBuilder\Schema\Fields\UrlField;
use Plugins\PageBuilder\Schema\Schema;

/** A logo image, optionally linked. */
class LogoBlock extends AbstractBlockDefinition
{
    public function type(): string
    {
        return 'logo';
    }

    public function label(): string
    {
        return 'Logo';
    }

    public function category(): string
    {
        return 'media';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-building-office';
    }

    public function defaults(): array
    {
        return ['src' => '', 'alt' => '', 'url' => ''];
    }

    public function schema(): array
    {
        return Schema::make()->fields([
            MediaField::make('src')->label('Logo image')->accept(['image/*']),
            TextField::make('alt')->label('Alt text')->translatable(),
            UrlField::make('url')->label('Link URL'),
        ])->toArray();
    }
}
