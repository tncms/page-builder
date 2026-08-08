<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Runtime;

use Plugins\PageBuilder\Contracts\ComponentInstance;
use Plugins\PageBuilder\Support\LayoutNormalizer;

/**
 * The instance tree for a layout payload (Phase 7): section instances, each with
 * block-instance children (and nested blocks under container blocks). Built by
 * normalizing the payload then constructing instances through the
 * {@see ComponentFactory}. Offers depth-first traversal (walk / flatten / find)
 * for introspection; the renderer walks `sections()` and hands each to the
 * {@see ComponentRuntime}.
 */
final class ComponentTree
{
    /**
     * @param  array<int, SectionInstance>  $sections
     */
    public function __construct(private readonly array $sections) {}

    public static function fromPayload(?array $payload, LayoutNormalizer $normalizer, ComponentFactory $factory): self
    {
        $layout = $normalizer->normalize($payload);

        $sections = [];
        foreach ($layout['sections'] as $node) {
            $sections[] = $factory->section($node);
        }

        return new self($sections);
    }

    /** @return array<int, SectionInstance> */
    public function sections(): array
    {
        return $this->sections;
    }

    public function isEmpty(): bool
    {
        return $this->sections === [];
    }

    /**
     * Depth-first visit of every instance (section → blocks → nested blocks).
     *
     * @param  callable(ComponentInstance):void  $visitor
     */
    public function walk(callable $visitor): void
    {
        foreach ($this->sections as $section) {
            $this->visit($section, $visitor);
        }
    }

    /**
     * Every instance in the tree, depth-first.
     *
     * @return array<int, ComponentInstance>
     */
    public function flatten(): array
    {
        $out = [];
        $this->walk(function (ComponentInstance $instance) use (&$out): void {
            $out[] = $instance;
        });

        return $out;
    }

    /**
     * The first instance matching the predicate, or null.
     *
     * @param  callable(ComponentInstance):bool  $predicate
     */
    public function find(callable $predicate): ?ComponentInstance
    {
        foreach ($this->flatten() as $instance) {
            if ($predicate($instance)) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * @param  callable(ComponentInstance):void  $visitor
     */
    private function visit(ComponentInstance $instance, callable $visitor): void
    {
        $visitor($instance);
        foreach ($instance->children() as $child) {
            $this->visit($child, $visitor);
        }
    }
}
