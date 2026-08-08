<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Schema\Fields;

/** Numeric field with optional min / max / step + unit hints. */
class NumberField extends Field
{
    protected string $type = 'number';

    public function min(int|float $min): static
    {
        return $this->editor(['min' => $min]);
    }

    public function max(int|float $max): static
    {
        return $this->editor(['max' => $max]);
    }

    public function step(int|float $step): static
    {
        return $this->editor(['step' => $step]);
    }

    /**
     * Selectable CSS units (e.g. ['px', 'rem', '%']).
     *
     * @param  array<int, string>  $units
     */
    public function units(array $units): static
    {
        return $this->editor(['units' => $units]);
    }
}
