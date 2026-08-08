<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Runtime;

use Plugins\PageBuilder\Registries\BlockRegistry;
use Plugins\PageBuilder\Registries\SectionRegistry;

/**
 * Builds component instances from payload nodes (Phase 7).
 *
 * Looks up the normalized definition array from the section/block registry (null
 * for unknown types) and constructs the matching instance, injecting itself so
 * instances can reach the resolvers + build their own children. Unknown types
 * still produce an instance (which renders nothing) — the runtime never crashes
 * on unfamiliar content.
 */
class ComponentFactory
{
    public function __construct(
        private readonly SectionRegistry $sections,
        private readonly BlockRegistry $blocks,
        private readonly ResponsiveResolver $responsive,
        private readonly DesignTokenResolver $tokens,
        private readonly DynamicValueResolver $dynamic,
        private readonly ConditionResolver $conditions,
    ) {}

    /**
     * @param  array<string, mixed>  $node  A normalized section node (type/id/data/blocks).
     */
    public function section(array $node): SectionInstance
    {
        $type = $this->typeOf($node);

        return (new SectionInstance($this->sections->get($type), $this))->hydrate($node);
    }

    /**
     * @param  array<string, mixed>  $node  A block node (type/id/data[, blocks]).
     */
    public function block(array $node): BlockInstance
    {
        $type = $this->typeOf($node);

        $normalized = [
            'type' => $type,
            'id' => $node['id'] ?? null,
            'data' => is_array($node['data'] ?? null) ? $node['data'] : [],
        ];

        return (new BlockInstance($this->blocks->get($type), $this))->hydrate($normalized);
    }

    public function responsiveResolver(): ResponsiveResolver
    {
        return $this->responsive;
    }

    public function tokenResolver(): DesignTokenResolver
    {
        return $this->tokens;
    }

    public function dynamicResolver(): DynamicValueResolver
    {
        return $this->dynamic;
    }

    public function conditionResolver(): ConditionResolver
    {
        return $this->conditions;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function typeOf(array $node): string
    {
        $type = $node['type'] ?? null;

        return is_string($type) ? trim($type) : '';
    }
}
