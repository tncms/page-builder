<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Schema;

use Plugins\PageBuilder\Schema\Fields\Field;

/**
 * The root of a component's editor schema (Phase 6).
 *
 * Holds one or more {@see Tab}s of fields. Definitions build a Schema fluently
 * and hand back `->toArray()` — always `{tabs: [...]}` — so the stored schema
 * shape is uniform whether a component has one tab or several. A convenience
 * `fields()` shorthand drops fields into an implicit "content" tab.
 */
final class Schema
{
    /** @var array<int, Tab> */
    private array $tabs = [];

    public static function make(): self
    {
        return new self;
    }

    public function tab(Tab $tab): self
    {
        $this->tabs[] = $tab;

        return $this;
    }

    /**
     * @param  array<int, Tab>  $tabs
     */
    public function tabs(array $tabs): self
    {
        foreach ($tabs as $tab) {
            if ($tab instanceof Tab) {
                $this->tabs[] = $tab;
            }
        }

        return $this;
    }

    /**
     * Shorthand: place fields into a single implicit "content" tab.
     *
     * @param  array<int, Field>  $fields
     */
    public function fields(array $fields): self
    {
        return $this->tab(Tab::make('content')->label('Content')->fields($fields));
    }

    /**
     * @return array{tabs: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'tabs' => array_map(static fn (Tab $tab): array => $tab->toArray(), $this->tabs),
        ];
    }
}
