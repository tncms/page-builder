<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Manifest;

/**
 * A single dependency declaration in a Resource Manifest (Phase 26.50A, Rule 6).
 *
 * A resource declares what it needs — another Builder resource today, and reserved
 * future plugins, extensions and themes. This is a DECLARATION for RESOLUTION only:
 * the foundation records dependencies so a resolver can reason about them; it does
 * NOT install anything (installation comes later).
 *
 * For a `resource` dependency the id is a RID (`<source>:<kind>:<resourceId>`); for
 * the reserved kinds it is a slug. The constraint uses the familiar version grammar
 * and is stored verbatim.
 */
final class ResourceDependency
{
    public const TYPE_RESOURCE = 'resource';

    public const TYPE_PLUGIN = 'plugin';       // reserved

    public const TYPE_EXTENSION = 'extension'; // reserved

    public const TYPE_THEME = 'theme';         // reserved

    public const TYPES = [self::TYPE_RESOURCE, self::TYPE_PLUGIN, self::TYPE_EXTENSION, self::TYPE_THEME];

    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly string $constraint = '*',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: is_string($data['type'] ?? null) ? $data['type'] : '',
            id: is_string($data['id'] ?? null) ? $data['id'] : '',
            constraint: is_string($data['constraint'] ?? null) && $data['constraint'] !== '' ? $data['constraint'] : '*',
        );
    }

    public function isResource(): bool
    {
        return $this->type === self::TYPE_RESOURCE;
    }

    public function isKnownType(): bool
    {
        return in_array($this->type, self::TYPES, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'constraint' => $this->constraint,
        ];
    }
}
