<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Schema\Fields;

/**
 * A data-source selector (PB-XREG-2).
 *
 * Stores `{source, limit, options}` in the node's existing `data` container under
 * this field's name. `accepts()` declares which record kinds are compatible; the
 * server publishes only matching registered sources to the editor and validates
 * every saved value against the chosen source's own declaration. The field never
 * carries records — they are fetched server-side on every render.
 */
class DataSourceField extends Field
{
    protected string $type = 'data-source';

    /**
     * @param  list<string>  $recordKinds
     */
    public function accepts(array $recordKinds): static
    {
        return $this->editor(['accepts' => array_values(array_unique(array_map('strval', $recordKinds)))]);
    }
}
