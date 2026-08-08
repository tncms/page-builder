<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Runtime;

/**
 * A block instance: an atomic (or container) piece of content. Container blocks
 * such as `column` hold nested blocks in their `data['blocks']`, which become
 * child instances for tree traversal. A block with no resolvable view renders
 * nothing (an authoring notice in debug) — handled by the {@see ComponentRuntime}.
 */
class BlockInstance extends AbstractComponentInstance
{
    public function kind(): string
    {
        return 'block';
    }

    public function renderView(): string
    {
        return $this->resolveThemeOrPlugin('blocks', $this->type) ?? '';
    }

    public function renderData(): array
    {
        return $this->baseRenderData('block');
    }

    protected function buildChildren(): array
    {
        $blocks = is_array($this->data['blocks'] ?? null) ? $this->data['blocks'] : [];

        $children = [];
        foreach ($blocks as $node) {
            if (is_array($node) && $this->hasType($node)) {
                $children[] = $this->factory->block($node);
            }
        }

        return $children;
    }
}
