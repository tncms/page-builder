<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Runtime;

/**
 * A section instance: a top-level container whose children are block instances
 * built from the payload's `blocks`. Its view resolves through the theme →
 * plugin chain and always falls back to the generic section wrapper.
 */
class SectionInstance extends AbstractComponentInstance
{
    public function kind(): string
    {
        return 'section';
    }

    public function renderView(): string
    {
        return $this->resolveThemeOrPlugin('sections', $this->type)
            ?? 'page-builder::sections.default';
    }

    public function renderData(): array
    {
        return $this->baseRenderData('section');
    }

    protected function buildChildren(): array
    {
        $blocks = is_array($this->payload['blocks'] ?? null) ? $this->payload['blocks'] : [];

        $children = [];
        foreach ($blocks as $node) {
            if (is_array($node) && $this->hasType($node)) {
                $children[] = $this->factory->block($node);
            }
        }

        return $children;
    }
}
