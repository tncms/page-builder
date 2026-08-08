<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Runtime;

use Plugins\PageBuilder\Contracts\ComponentInstance;

/**
 * Lifecycle hook bus for the component runtime (Phase 7).
 *
 * Plugins/themes register callbacks against the four lifecycle points —
 * beforeResolve / afterResolve / beforeRender / afterRender — and the
 * {@see ComponentRuntime} dispatches them around each instance's lifecycle. A
 * bound singleton, so registrations persist for the request. Callbacks receive
 * the {@see ComponentInstance} and may mutate it (resolved data, visibility, …).
 */
class RuntimeHookManager
{
    public const POINTS = ['beforeResolve', 'afterResolve', 'beforeRender', 'afterRender'];

    /** @var array<string, array<int, callable>> */
    private array $hooks = [];

    public function on(string $point, callable $callback): static
    {
        if (in_array($point, self::POINTS, true)) {
            $this->hooks[$point][] = $callback;
        }

        return $this;
    }

    public function dispatch(string $point, ComponentInstance $instance): void
    {
        foreach ($this->hooks[$point] ?? [] as $callback) {
            $callback($instance);
        }
    }

    public function count(string $point): int
    {
        return count($this->hooks[$point] ?? []);
    }

    public function flush(?string $point = null): static
    {
        if ($point === null) {
            $this->hooks = [];
        } else {
            unset($this->hooks[$point]);
        }

        return $this;
    }
}
