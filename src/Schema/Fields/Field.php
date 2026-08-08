<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Schema\Fields;

use Illuminate\Support\Str;

/**
 * Base editor field (Phase 6 — Schema-Driven Definitions).
 *
 * A fluent, framework-agnostic descriptor of a single editable field. It is
 * pure metadata: `toArray()` emits a plain map the future Vue editor renders —
 * no rendering, validation or Filament coupling here. Subclasses only set their
 * `$type` (and occasionally add a couple of type-specific setters).
 */
class Field
{
    protected string $type = 'text';

    protected ?string $label = null;

    protected mixed $default = null;

    protected bool $required = false;

    /** @var array<int|string, mixed> */
    protected array $options = [];

    protected bool $responsive = false;

    protected bool $translatable = false;

    protected ?string $help = null;

    /** @var array<int, string> */
    protected array $rules = [];

    /** @var array<string, mixed> */
    protected array $editor = [];

    public function __construct(protected string $name) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    /**
     * @param  array<int|string, mixed>  $options
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function responsive(bool $responsive = true): static
    {
        $this->responsive = $responsive;

        return $this;
    }

    public function translatable(bool $translatable = true): static
    {
        $this->translatable = $translatable;

        return $this;
    }

    public function help(?string $help): static
    {
        $this->help = $help;

        return $this;
    }

    /**
     * @param  array<int, string>  $rules
     */
    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * Merge additional editor-only metadata for this field.
     *
     * @param  array<string, mixed>  $editor
     */
    public function editor(array $editor): static
    {
        $this->editor = array_merge($this->editor, $editor);

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'label' => $this->label ?? Str::headline($this->name),
            'default' => $this->default,
            'required' => $this->required,
            'options' => $this->options,
            'responsive' => $this->responsive,
            'translatable' => $this->translatable,
            'help' => $this->help,
            'rules' => $this->rules,
            'editor' => $this->editor,
        ];
    }
}
