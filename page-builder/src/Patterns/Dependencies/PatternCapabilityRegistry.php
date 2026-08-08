<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Dependencies;

/**
 * Registry of capability keys the environment supports (Phase 25.2, Part 1). A
 * "capability" is an abstract feature a pattern may require (e.g. `rtl`, `dark-mode`,
 * `dynamic-data`) that is not a plugin or a component. Plugins/themes register the
 * capabilities they provide; the {@see PatternDependencyResolver} treats any
 * unregistered capability as missing. Empty by default (extensible seam).
 */
final class PatternCapabilityRegistry
{
    /** @var array<string, bool> */
    private array $capabilities = [];

    public function register(string $capability): self
    {
        $capability = trim($capability);
        if ($capability !== '') {
            $this->capabilities[$capability] = true;
        }

        return $this;
    }

    public function has(string $capability): bool
    {
        return isset($this->capabilities[trim($capability)]);
    }

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        return array_keys($this->capabilities);
    }
}
