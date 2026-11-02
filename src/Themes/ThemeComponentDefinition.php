<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Themes;

use Plugins\PageBuilder\Components\Contracts\ExternalComponentDefinition;
use Plugins\PageBuilder\Definitions\AbstractComponentDefinition;

/**
 * A validated, immutable component declared by a theme (PB-XREG-4). Built only by {@see ThemeComponentFactory}
 * from a theme's declarative file; the four concrete classes only differ in which frozen kind/interactive
 * interfaces they implement, so the existing registry, runtime and editor treat them exactly like a plugin's
 * external component.
 *
 * The renderer view is fixed by convention — `theme::page-builder.components.{slug}.{name}` — and resolves
 * through Core's `theme::` namespace, i.e. only inside the active theme chain.
 */
abstract class ThemeComponentDefinition extends AbstractComponentDefinition implements ExternalComponentDefinition
{
    public const VIEW_PREFIX = 'theme::page-builder.components.';

    /**
     * @param  array<string, string>  $labels  locale => plain-text label (non-empty)
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $allowedBlocks
     */
    final public function __construct(
        private readonly string $themeSlug,
        private readonly string $name,
        private readonly array $labels,
        private readonly string $category,
        private readonly ?string $icon,
        private readonly array $defaults,
        private readonly array $schema,
        private readonly array $allowedBlocks = [],
        private readonly mixed $behavior = null,
    ) {}

    public function type(): string
    {
        return $this->themeSlug.':'.$this->name;
    }

    /** The label for the current application locale, else the first declared one. */
    public function label(): string
    {
        return $this->labels[app()->getLocale()] ?? $this->labels[array_key_first($this->labels)];
    }

    public function category(): string
    {
        return $this->category;
    }

    public function icon(): ?string
    {
        return $this->icon;
    }

    public function defaults(): array
    {
        return $this->defaults;
    }

    public function schema(): array
    {
        return $this->schema;
    }

    /** The per-locale labels, so the editor can show the Admin UI locale (PB-XREG-4). */
    public function editor(): array
    {
        return ['labels' => $this->labels];
    }

    public function view(): string
    {
        return self::VIEW_PREFIX.$this->themeSlug.'.'.$this->name;
    }

    /** @return list<string> */
    public function allowedBlocks(): array
    {
        return $this->allowedBlocks;
    }

    public function themeSlug(): string
    {
        return $this->themeSlug;
    }

    protected function declaredBehavior(): mixed
    {
        return $this->behavior;
    }
}
