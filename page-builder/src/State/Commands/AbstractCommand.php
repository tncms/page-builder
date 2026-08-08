<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

use Plugins\PageBuilder\State\ComponentLocator;
use Plugins\PageBuilder\State\LayoutMutator;

/**
 * Shared boilerplate for the built-in commands (Phase 8): lazily-created
 * {@see LayoutMutator} / {@see ComponentLocator} helpers and a default empty
 * {@see payload()}.
 */
abstract class AbstractCommand implements Command
{
    private ?LayoutMutator $mutator = null;

    private ?ComponentLocator $locator = null;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [];
    }

    protected function mutator(): LayoutMutator
    {
        return $this->mutator ??= new LayoutMutator();
    }

    protected function locator(): ComponentLocator
    {
        return $this->locator ??= new ComponentLocator();
    }
}
