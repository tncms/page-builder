<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Dependencies;

/**
 * Outcome of checking a pattern's dependencies (Phase 25.2, Part 1). `available`
 * is true only when nothing is missing; the missing lists explain WHY a pattern is
 * unavailable so the library can disable it with a reason and the API can refuse
 * insertion.
 */
final class PatternDependencyResult
{
    /**
     * @param  array<int, string>  $missingPlugins
     * @param  array<int, string>  $missingComponents
     * @param  array<int, string>  $missingCapabilities
     * @param  array<int, PatternRequirement>  $requirements  Full requirement contract (Phase 25.2.x).
     */
    public function __construct(
        public readonly array $missingPlugins = [],
        public readonly array $missingComponents = [],
        public readonly array $missingCapabilities = [],
        public readonly array $requirements = [],
    ) {}

    /**
     * The full requirement list (met + missing), UI-ready.
     *
     * @return array<int, PatternRequirement>
     */
    public function requirements(): array
    {
        return $this->requirements;
    }

    public function available(): bool
    {
        return $this->missingPlugins === []
            && $this->missingComponents === []
            && $this->missingCapabilities === [];
    }

    /**
     * @return array{available: bool, missing: array{plugins: array<int, string>, components: array<int, string>, capabilities: array<int, string>}, requirements: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'available' => $this->available(),
            'missing' => [
                'plugins' => $this->missingPlugins,
                'components' => $this->missingComponents,
                'capabilities' => $this->missingCapabilities,
            ],
            'requirements' => array_map(
                static fn (PatternRequirement $requirement): array => $requirement->toArray(),
                $this->requirements,
            ),
        ];
    }
}
