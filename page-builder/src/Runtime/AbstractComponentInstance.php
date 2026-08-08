<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Runtime;

use Illuminate\Support\Facades\View;
use Plugins\PageBuilder\Contracts\ComponentInstance;

/**
 * Base component instance (Phase 7).
 *
 * Holds the payload + resolved data and drives the resolve lifecycle in a fixed
 * order: hydrate → beforeResolve → resolveDefaults → resolveResponsive →
 * resolveTokens → resolveDynamicData → resolveConditions → afterResolve, then
 * (at render time) beforeRender → afterRender. Each step records its name in a
 * lifecycle trace for observability. In Phase 7 every resolver is effectively an
 * identity for the built-in library, so merging defaults + resolving produces
 * the same view data the pre-runtime renderer passed.
 *
 * Subclasses ({@see SectionInstance}, {@see BlockInstance}) supply their kind,
 * view resolution and child building; resolvers + child construction are reached
 * through the {@see ComponentFactory}.
 */
abstract class AbstractComponentInstance implements ComponentInstance
{
    protected string $type = '';

    protected ?string $id = null;

    /** @var array<string, mixed> */
    protected array $payload = [];

    /** @var array<string, mixed> */
    protected array $data = [];

    /** @var array<string, mixed> */
    protected array $resolvedData = [];

    protected bool $visible = true;

    protected bool $resolved = false;

    /** @var array<int, ComponentInstance>|null Lazily built child instances. */
    protected ?array $children = null;

    /** @var array<int, string> Ordered lifecycle event log. */
    protected array $trace = [];

    /**
     * @param  array<string, mixed>|null  $definition  Normalized definition array, or null for unknown types.
     */
    public function __construct(
        protected readonly ?array $definition,
        protected readonly ComponentFactory $factory,
    ) {}

    abstract public function kind(): string;

    abstract public function renderView(): string;

    abstract public function renderData(): array;

    /** @return array<int, ComponentInstance> */
    abstract protected function buildChildren(): array;

    public function hydrate(array $payload): static
    {
        $this->payload = $payload;
        $this->type = is_string($payload['type'] ?? null) ? trim($payload['type']) : '';
        $this->id = $this->coerceId($payload['id'] ?? null);
        $this->data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $this->resolvedData = $this->data;
        $this->trace[] = 'hydrate';

        return $this;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function id(): ?string
    {
        return $this->id;
    }

    public function definition(): ?array
    {
        return $this->definition;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function defaults(): array
    {
        return is_array($this->definition['defaults'] ?? null) ? $this->definition['defaults'] : [];
    }

    public function supports(): array
    {
        return is_array($this->definition['supports'] ?? null) ? $this->definition['supports'] : [];
    }

    public function assets(): array
    {
        return is_array($this->definition['assets'] ?? null)
            ? $this->definition['assets']
            : ['css' => [], 'js' => [], 'editor_css' => [], 'editor_js' => []];
    }

    public function resolvedData(): array
    {
        return $this->resolvedData;
    }

    public function children(): array
    {
        if ($this->children === null) {
            $this->children = $this->buildChildren();
        }

        return $this->children;
    }

    public function addChild(ComponentInstance $child): static
    {
        $this->children = [...$this->children(), $child];

        return $this;
    }

    public function removeChild(ComponentInstance $child): static
    {
        $this->children = array_values(array_filter(
            $this->children(),
            static fn (ComponentInstance $existing): bool => $existing !== $child,
        ));

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    /** @return array<int, string> */
    public function lifecycleTrace(): array
    {
        return $this->trace;
    }

    public function resolve(): static
    {
        if ($this->resolved) {
            return $this;
        }

        $this->beforeResolve();
        $this->resolveDefaults();
        $this->resolveResponsive();
        $this->resolveTokens();
        $this->resolveDynamicData();
        $this->resolveConditions();
        $this->afterResolve();

        $this->resolved = true;

        return $this;
    }

    // ---- Resolve steps (Phase 7: identity for the built-in library) -----

    protected function resolveDefaults(): void
    {
        $this->trace[] = 'resolveDefaults';
        $this->resolvedData = array_merge($this->defaults(), $this->resolvedData);
    }

    protected function resolveResponsive(): void
    {
        $this->trace[] = 'resolveResponsive';
        $this->resolvedData = $this->factory->responsiveResolver()->resolveData($this->resolvedData);
    }

    protected function resolveTokens(): void
    {
        $this->trace[] = 'resolveTokens';
        $this->resolvedData = $this->factory->tokenResolver()->resolveData($this->resolvedData);
    }

    protected function resolveDynamicData(): void
    {
        $this->trace[] = 'resolveDynamicData';
        $this->resolvedData = $this->factory->dynamicResolver()->resolveData($this->resolvedData, $this);
    }

    protected function resolveConditions(): void
    {
        $this->trace[] = 'resolveConditions';
        $this->visible = $this->factory->conditionResolver()->resolve($this);
    }

    // ---- Lifecycle hook points (overridable; trace-recording) -----------

    public function beforeResolve(): void
    {
        $this->trace[] = 'beforeResolve';
    }

    public function afterResolve(): void
    {
        $this->trace[] = 'afterResolve';
    }

    public function beforeRender(): void
    {
        $this->trace[] = 'beforeRender';
    }

    public function afterRender(): void
    {
        $this->trace[] = 'afterRender';
    }

    // ---- Helpers --------------------------------------------------------

    /**
     * Resolve the active-theme override then the plugin view for a kind/type.
     */
    protected function resolveThemeOrPlugin(string $kind, string $type): ?string
    {
        $themeView = "theme::page-builder.{$kind}.{$type}";
        if (View::exists($themeView)) {
            return $themeView;
        }

        $pluginView = "page-builder::{$kind}.{$type}";
        if (View::exists($pluginView)) {
            return $pluginView;
        }

        return null;
    }

    /**
     * The base view data shared by sections + blocks.
     *
     * @return array<string, mixed>
     */
    protected function baseRenderData(string $nodeKey): array
    {
        return [
            $nodeKey => ['type' => $this->type, 'id' => $this->id, 'data' => $this->resolvedData],
            'type' => $this->type,
            'data' => $this->resolvedData,
        ];
    }

    protected function hasType(array $node): bool
    {
        $type = $node['type'] ?? null;

        return is_string($type) && trim($type) !== '';
    }

    private function coerceId(mixed $id): ?string
    {
        if (is_string($id) && trim($id) !== '') {
            return trim($id);
        }

        return is_int($id) ? (string) $id : null;
    }
}
