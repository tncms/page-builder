<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Dependencies;

use Closure;
use Plugins\PageBuilder\Patterns\Pattern;
use Plugins\PageBuilder\Registries\BlockRegistry;
use Plugins\PageBuilder\Registries\SectionRegistry;

/**
 * Decides whether a pattern can be inserted in the current environment (Phase 25.2,
 * Part 1). Plugin availability is checked through an injected callback (so the
 * resolver stays decoupled from the CMS extension manager and is unit-testable);
 * components resolve against the section/block registries; capabilities against the
 * {@see PatternCapabilityRegistry}. A pattern with no dependencies is always
 * available.
 */
final class PatternDependencyResolver
{
    /** @var Closure(string): bool */
    private readonly Closure $pluginChecker;

    /**
     * @param  (callable(string): bool)|null  $pluginChecker  Returns true when a plugin is active.
     */
    public function __construct(
        private readonly SectionRegistry $sections,
        private readonly BlockRegistry $blocks,
        private readonly PatternCapabilityRegistry $capabilities,
        ?callable $pluginChecker = null,
    ) {
        $this->pluginChecker = $pluginChecker !== null
            ? Closure::fromCallable($pluginChecker)
            : static fn (string $plugin): bool => true;
    }

    public function check(Pattern $pattern): PatternDependencyResult
    {
        return $this->checkDependencies($pattern->manifest()->dependencies);
    }

    public function checkDependencies(PatternDependencies $dependencies): PatternDependencyResult
    {
        if ($dependencies->isEmpty()) {
            return new PatternDependencyResult();
        }

        $requirements = [];
        $missingPlugins = [];
        $missingComponents = [];
        $missingCapabilities = [];

        foreach ($dependencies->plugins as $plugin) {
            $met = (bool) ($this->pluginChecker)($plugin);
            $requirements[] = PatternRequirement::make('plugin', $plugin, $met);
            if (! $met) {
                $missingPlugins[] = $plugin;
            }
        }
        foreach ($dependencies->components as $component) {
            $met = $this->sections->has($component) || $this->blocks->has($component);
            $requirements[] = PatternRequirement::make('component', $component, $met);
            if (! $met) {
                $missingComponents[] = $component;
            }
        }
        foreach ($dependencies->capabilities as $capability) {
            $met = $this->capabilities->has($capability);
            $requirements[] = PatternRequirement::make('capability', $capability, $met);
            if (! $met) {
                $missingCapabilities[] = $capability;
            }
        }

        return new PatternDependencyResult(
            missingPlugins: $missingPlugins,
            missingComponents: $missingComponents,
            missingCapabilities: $missingCapabilities,
            requirements: $requirements,
        );
    }

    public function isAvailable(Pattern $pattern): bool
    {
        return $this->check($pattern)->available();
    }
}
