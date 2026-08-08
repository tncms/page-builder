<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Schema\Fields;

/**
 * A wrapper that makes an inner field responsive: the editor renders the inner
 * field once per breakpoint (desktop/tablet/mobile) and stores a
 * {@see \Plugins\PageBuilder\Support\ResponsiveValue}-shaped value.
 */
class ResponsiveField extends Field
{
    protected string $type = 'responsive';

    protected bool $responsive = true;

    protected ?Field $field = null;

    /** Set the inner field that is repeated per breakpoint. */
    public function field(Field $field): static
    {
        $this->field = $field;

        return $this;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'field' => $this->field?->toArray(),
        ]);
    }
}
