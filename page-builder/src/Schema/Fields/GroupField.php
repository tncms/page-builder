<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Schema\Fields;

/**
 * A field that groups several sub-fields under a single key (a nested object in
 * the stored data). Useful for compound settings such as a "link" (url + label
 * + target).
 */
class GroupField extends Field
{
    protected string $type = 'group';

    /** @var array<int, Field> */
    protected array $fields = [];

    /**
     * @param  array<int, Field>  $fields
     */
    public function fields(array $fields): static
    {
        $this->fields = array_values(array_filter(
            $fields,
            static fn ($field): bool => $field instanceof Field,
        ));

        return $this;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'fields' => array_map(static fn (Field $field): array => $field->toArray(), $this->fields),
        ]);
    }
}
