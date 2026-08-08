<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Dependencies;

/**
 * Immutable declaration of what a pattern needs before it can be inserted
 * (Phase 25.2, Part 1): active plugins, registered components (section/block
 * types), and supported capabilities. Every list is optional — a pattern with no
 * `dependencies` block has none, so existing patterns are unaffected.
 */
final class PatternDependencies
{
    /**
     * @param  array<int, string>  $plugins
     * @param  array<int, string>  $components
     * @param  array<int, string>  $capabilities
     */
    public function __construct(
        public readonly array $plugins = [],
        public readonly array $components = [],
        public readonly array $capabilities = [],
    ) {}

    /**
     * @param  mixed  $data
     */
    public static function fromArray(mixed $data): self
    {
        $data = is_array($data) ? $data : [];

        return new self(
            plugins: self::stringList($data['plugins'] ?? []),
            components: self::stringList($data['components'] ?? []),
            capabilities: self::stringList($data['capabilities'] ?? []),
        );
    }

    public function isEmpty(): bool
    {
        return $this->plugins === [] && $this->components === [] && $this->capabilities === [];
    }

    /**
     * @return array{plugins: array<int, string>, components: array<int, string>, capabilities: array<int, string>}
     */
    public function toArray(): array
    {
        return [
            'plugins' => $this->plugins,
            'components' => $this->components,
            'capabilities' => $this->capabilities,
        ];
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($v): string => is_scalar($v) ? trim((string) $v) : '',
            $value,
        ), static fn (string $v): bool => $v !== '')));
    }
}
