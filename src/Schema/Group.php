<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Schema;

use Illuminate\Support\Str;
use Plugins\PageBuilder\Schema\Fields\Field;

/**
 * A visual fieldset that groups related fields inside a tab (e.g. "Spacing").
 * Unlike {@see \Plugins\PageBuilder\Schema\Fields\GroupField} it does NOT nest
 * the data under its own key — it is a presentation-only grouping.
 */
final class Group
{
    private ?string $label = null;

    /** @var array<int, Field> */
    private array $fields = [];

    public function __construct(private readonly string $name) {}

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function label(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @param  array<int, Field>  $fields
     */
    public function fields(array $fields): self
    {
        $this->fields = array_values(array_filter(
            $fields,
            static fn ($field): bool => $field instanceof Field,
        ));

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'group',
            'name' => $this->name,
            'label' => $this->label ?? Str::headline($this->name),
            'fields' => array_map(static fn (Field $field): array => $field->toArray(), $this->fields),
        ];
    }
}
