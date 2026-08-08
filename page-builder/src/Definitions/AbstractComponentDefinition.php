<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Definitions;

use Plugins\PageBuilder\Contracts\ComponentDefinition;
use Plugins\PageBuilder\Schema\Schema;

/**
 * Base class for section & block definitions (Phase 6).
 *
 * Supplies safe defaults for every optional facet so a concrete definition only
 * has to declare its identity + schema. `toArray()` produces the normalized
 * shape the registries understand, and always guarantees the four-key asset
 * shape (css / js / editor_css / editor_js) even when a definition returns a
 * partial or empty asset map.
 */
abstract class AbstractComponentDefinition implements ComponentDefinition
{
    abstract public function type(): string;

    abstract public function label(): string;

    public function category(): string
    {
        return 'general';
    }

    public function icon(): ?string
    {
        return null;
    }

    public function defaults(): array
    {
        return [];
    }

    public function schema(): array
    {
        return Schema::make()->toArray();
    }

    public function supports(): array
    {
        return [];
    }

    public function assets(): array
    {
        return self::emptyAssets();
    }

    public function editor(): array
    {
        return [];
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type(),
            'label' => $this->label(),
            'category' => $this->category(),
            'icon' => $this->icon(),
            'defaults' => $this->defaults(),
            'schema' => $this->schema(),
            'supports' => $this->supports(),
            'assets' => array_merge(self::emptyAssets(), $this->assets()),
            'editor' => $this->editor(),
        ];
    }

    /**
     * The canonical empty asset shape.
     *
     * @return array{css: array<int, string>, js: array<int, string>, editor_css: array<int, string>, editor_js: array<int, string>}
     */
    protected static function emptyAssets(): array
    {
        return ['css' => [], 'js' => [], 'editor_css' => [], 'editor_js' => []];
    }
}
