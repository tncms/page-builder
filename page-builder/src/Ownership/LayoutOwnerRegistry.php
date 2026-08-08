<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Ownership;

/**
 * The generic registry of known Layout Owner types (LT-3, Option D).
 *
 * Layer: Page Builder core (Ownership). The single source of truth for which
 * owner types are valid layout-storage attachment points. Page Builder registers
 * its own content owners (`page`, `post`); other plugins register their owner
 * types (e.g. the Translation layer registers `content_translation`) without any
 * Page Builder change. This registry never imports or understands Translation.
 */
final class LayoutOwnerRegistry
{
    /** @var array<string, true> */
    private array $types = [];

    public function register(string $ownerType): void
    {
        $type = trim($ownerType);
        if ($type !== '') {
            $this->types[$type] = true;
        }
    }

    public function isRegistered(string $ownerType): bool
    {
        return isset($this->types[trim($ownerType)]);
    }

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        return array_keys($this->types);
    }
}
