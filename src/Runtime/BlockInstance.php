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
        // PB-XREG-1 — external components resolve ONLY from trusted registration;
        // their namespaced id never enters the theme/plugin view convention. An
        // unavailable external block (provider absent) renders nothing, not a crash.
        if ($this->isExternalType()) {
            return $this->trustedExternalView() ?? '';
        }

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
