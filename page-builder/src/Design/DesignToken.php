<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Design;

/**
 * A single design token (Phase 6): a named value within a group, e.g.
 * group "color" / name "primary" / value "#2563eb". Its `key()` ("color.primary")
 * is the reference authors bind fields to. Value may be a scalar or an array
 * (e.g. a typography token). Immutable value object.
 */
final class DesignToken
{
    public function __construct(
        private readonly string $group,
        private readonly string $name,
        private readonly mixed $value,
        private readonly ?string $label = null,
    ) {}

    public function group(): string
    {
        return $this->group;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** Dotted reference, e.g. "color.primary". */
    public function key(): string
    {
        return "{$this->group}.{$this->name}";
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function label(): string
    {
        return $this->label ?? ucfirst($this->name);
    }

    /**
     * @return array{group: string, name: string, key: string, value: mixed, label: string}
     */
    public function toArray(): array
    {
        return [
            'group' => $this->group,
            'name' => $this->name,
            'key' => $this->key(),
            'value' => $this->value,
            'label' => $this->label(),
        ];
    }
}
