<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

/**
 * One allow-listed, declarative query option a source exposes (PB-XREG-2).
 *
 * Only two shapes exist — a fixed enum (`choices` value => label) and a boolean —
 * so a layout can never carry an operator, expression, column, relation, regex,
 * URL, or class name. Bounds are enforced by the compliance validator.
 */
final class DataSourceOption
{
    public const TYPE_ENUM = 'enum';

    public const TYPE_BOOLEAN = 'boolean';

    /**
     * @param  array<string, string>  $choices  value => label (enum only)
     */
    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type,
        public readonly array $choices,
        public readonly string|bool $default,
    ) {}

    /**
     * @param  array<string, string>  $choices  value => label; `$default` must be one of the values
     */
    public static function enum(string $key, string $label, array $choices, string $default): self
    {
        return new self($key, $label, self::TYPE_ENUM, $choices, $default);
    }

    public static function boolean(string $key, string $label, bool $default = false): self
    {
        return new self($key, $label, self::TYPE_BOOLEAN, [], $default);
    }

    /** True when `$value` is an allowed value for this option. */
    public function accepts(mixed $value): bool
    {
        if ($this->type === self::TYPE_BOOLEAN) {
            return is_bool($value);
        }

        return is_string($value) && array_key_exists($value, $this->choices);
    }

    /**
     * @return array{key: string, label: string, type: string, choices: list<array{value: string, label: string}>, default: string|bool}
     */
    public function toArray(): array
    {
        $choices = [];
        foreach ($this->choices as $value => $label) {
            $choices[] = ['value' => (string) $value, 'label' => (string) $label];
        }

        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'choices' => $choices,
            'default' => $this->default,
        ];
    }
}
