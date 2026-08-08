<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Schema;

use Illuminate\Support\Str;

/**
 * A named tab in a component's editor schema, holding fields and/or groups.
 * Purely descriptive — serialised to an array for the future Vue editor.
 */
final class Tab
{
    private ?string $label = null;

    private ?string $icon = null;

    /** @var array<int, object> Field | Group instances (anything with toArray()). */
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

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @param  array<int, object>  $fields  Field or Group instances.
     */
    public function fields(array $fields): self
    {
        $this->fields = array_values(array_filter(
            $fields,
            static fn ($field): bool => is_object($field) && method_exists($field, 'toArray'),
        ));

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label ?? Str::headline($this->name),
            'icon' => $this->icon,
            'fields' => array_map(static fn ($field): array => $field->toArray(), $this->fields),
        ];
    }
}
