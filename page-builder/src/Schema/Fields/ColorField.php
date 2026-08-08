<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Schema\Fields;

/** Colour picker field; may be bound to a design token (e.g. "color.primary"). */
class ColorField extends Field
{
    protected string $type = 'color';

    /**
     * Advertise that this colour should default to / prefer a design token.
     */
    public function token(string $token): static
    {
        return $this->editor(['token' => $token]);
    }
}
