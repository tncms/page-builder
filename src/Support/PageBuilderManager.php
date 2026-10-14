<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use Plugins\PageBuilder\Design\TokenRegistry;
use Plugins\PageBuilder\Presets\ColumnLayoutPresetRegistry;
use Plugins\PageBuilder\Registries\BlockRegistry;
use Plugins\PageBuilder\Registries\SectionRegistry;
use Plugins\PageBuilder\Runtime\ComponentFactory;
use Plugins\PageBuilder\Runtime\ComponentRuntime;
use Plugins\PageBuilder\Runtime\ComponentTree;
use Plugins\PageBuilder\State\BuilderState;
use Plugins\PageBuilder\State\CommandBus;
use Plugins\PageBuilder\State\ComponentLocator;
use Plugins\PageBuilder\State\LayoutMutator;

/**
 * The Page Builder service (bound as the "page-builder" singleton and fronted by
 * the {@see \Plugins\PageBuilder\Facades\PageBuilder} facade).
 *
 * Phase 1 is a thin coordinator over the two registries plus a status snapshot
 * for the admin dashboard. It holds NO rendering, persistence or editor logic —
 * those are later phases. The builder's source of truth is a structured layout
 * tree (sections → blocks), never HTML.
 */
class PageBuilderManager
{
    public const VERSION = '0.1.0-alpha.4';

    /** Lazily-created Phase 8 state helpers (stateless singletons). */
    private ?CommandBus $commandBus = null;

    private ?LayoutMutator $mutator = null;

    private ?ComponentLocator $locator = null;

    public function __construct(
        private readonly SectionRegistry $sections,
        private readonly BlockRegistry $blocks,
        private readonly LayoutStore $layouts,
        private readonly LayoutRenderer $renderer,
        private readonly TokenRegistry $tokens,
        private readonly ComponentFactory $factory,
        private readonly ComponentRuntime $runtime,
        private readonly ColumnLayoutPresetRegistry $columnLayoutPresets,
        // Phase 26.47E — optional so existing construction paths stay valid; when
        // absent the editor simply receives an empty builderResources palette.
        private readonly ?\Plugins\PageBuilder\Builder\Registry\BuilderResourceRegistry $builderResources = null,
    ) {}

    /**
     * The section type registry.
     */
    public function sections(): SectionRegistry
    {
        return $this->sections;
    }

    /**
     * The block type registry.
     */
    public function blocks(): BlockRegistry
    {
        return $this->blocks;
    }

    /**
     * The layout persistence gateway (Phase 2 — Layout Storage).
     */
    public function layouts(): LayoutStore
    {
        return $this->layouts;
    }

    /**
     * The server-side layout renderer (Phase 3).
     */
    public function renderer(): LayoutRenderer
    {
        return $this->renderer;
    }

    /**
     * A fresh editor {@see BuilderState} for a (nullable/partial) payload (Phase 8).
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function state(?array $payload = null): BuilderState
    {
        return BuilderState::make($payload);
    }

    /**
     * The command bus that runs editor commands against a state (Phase 8).
     */
    public function commandBus(): CommandBus
    {
        return $this->commandBus ??= new CommandBus();
    }

    /**
     * The layout mutation helper (Phase 8).
     */
    public function mutator(): LayoutMutator
    {
        return $this->mutator ??= new LayoutMutator();
    }

    /**
     * The component locator (Phase 8).
     */
    public function locator(): ComponentLocator
    {
        return $this->locator ??= new ComponentLocator();
    }

    /**
     * The component instance factory (Phase 7).
     */
    public function factory(): ComponentFactory
    {
        return $this->factory;
    }

    /**
     * The component runtime executor (Phase 7).
     */
    public function runtime(): ComponentRuntime
    {
        return $this->runtime;
    }

    /**
     * Build the component instance tree for a (nullable/partial) payload (Phase 7).
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function componentTree(?array $payload): ComponentTree
    {
        return $this->renderer->tree($payload);
    }

    /**
     * Render a (nullable/partial) layout payload to HTML.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function render(?array $payload): string
    {
        return $this->renderer->render($payload);
    }

    /**
     * Render a payload to HTML with editor markers (Phase 12 visual preview).
     * Public {@see render()} output is unaffected.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function renderForEditor(?array $payload): string
    {
        return $this->renderer->renderForEditor($payload);
    }

    /**
     * Render a single component (by id) from a full payload to editor-marked HTML
     * (Phase 21 — Live Component Runtime). Returns null when the component can't
     * be found. Public {@see render()} output is unaffected.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function renderComponentForEditor(?array $payload, string $componentId): ?string
    {
        return $this->renderer->renderComponentForEditor($payload, $componentId);
    }

    /**
     * Configured frontend asset URLs, with nulls filtered out (Phase 3). Empty
     * when none are configured, so nothing is emitted by default.
     *
     * @return array<string, string>
     */
    public function assets(): array
    {
        $assets = config('page-builder.assets', []);

        if (! is_array($assets)) {
            return [];
        }

        return array_filter(
            $assets,
            static fn ($url): bool => is_string($url) && $url !== '',
        );
    }

    /**
     * Convenience: register a section type (delegates to {@see SectionRegistry}).
     *
     * @param  array<string, mixed>|\Plugins\PageBuilder\Contracts\ComponentDefinition|class-string  $definition
     */
    public function registerSection(string $type, array|\Plugins\PageBuilder\Contracts\ComponentDefinition|string $definition = []): static
    {
        $this->sections->register($type, $definition);

        return $this;
    }

    /**
     * Convenience: register a block type (delegates to {@see BlockRegistry}).
     *
     * @param  array<string, mixed>|\Plugins\PageBuilder\Contracts\ComponentDefinition|class-string  $definition
     */
    public function registerBlock(string $type, array|\Plugins\PageBuilder\Contracts\ComponentDefinition|string $definition = []): static
    {
        $this->blocks->register($type, $definition);

        return $this;
    }

    /**
     * The design-token registry (Phase 6).
     */
    public function tokenRegistry(): TokenRegistry
    {
        return $this->tokens;
    }

    /**
     * The design tokens as a grouped {group: {name: value}} map.
     *
     * @return array<string, array<string, mixed>>
     */
    public function tokens(): array
    {
        return $this->tokens->toArray();
    }

    /**
     * All registered section definitions as normalized arrays (Phase 6).
     *
     * @return array<int, array<string, mixed>>
     */
    public function sectionDefinitions(): array
    {
        return array_values($this->sections->all());
    }

    /**
     * All registered block definitions as normalized arrays (Phase 6).
     *
     * @return array<int, array<string, mixed>>
     */
    public function blockDefinitions(): array
    {
        return array_values($this->blocks->all());
    }

    /**
     * Both definition sets, for editor bootstrapping.
     *
     * @return array{sections: array<int, array<string, mixed>>, blocks: array<int, array<string, mixed>>}
     */
    public function definitions(): array
    {
        return [
            'sections' => $this->sectionDefinitions(),
            'blocks' => $this->blockDefinitions(),
        ];
    }

    /**
     * The full metadata bundle the editor bootstraps from: version, every
     * section/block definition (with schema), design tokens and breakpoints
     * (Phase 6/9). Shared by the editor-metadata and editor-context endpoints.
     *
     * @return array{version: string, sections: array<int, array<string, mixed>>, blocks: array<int, array<string, mixed>>, tokens: array<string, array<string, mixed>>, breakpoints: array<string, mixed>, presets: array<int, array<string, mixed>>, columnLayouts: array<int, array<string, mixed>>, layoutCategories: array<int, array{id: string, label: string}>}
     */
    public function editorMetadata(): array
    {
        return [
            'version' => self::VERSION,
            'sections' => $this->sectionDefinitions(),
            'blocks' => $this->blockDefinitions(),
            'tokens' => $this->tokens(),
            'breakpoints' => $this->tokens->breakpoints(),
            // Phase 25.3 — layout presets (Section → Container → Column trees) the
            // palette inserts via the existing insert-pattern command (one undo).
            'presets' => LayoutPresets::all(),
            // Phase 26.1 — column-proportion presets for the canvas Layout popup,
            // applied to an existing container via apply-column-layout (one undo).
            // Phase 26.1.1 — sourced from the provider registry so plugins/themes
            // can contribute presets; categories drive the popup's tabs.
            'columnLayouts' => $this->columnLayoutPresets->all(),
            'layoutCategories' => $this->columnLayoutPresets->categories(),
            // Phase 26.47E — Builder Resource Registry: grouped, categorised
            // design-resource sections/blocks the palette inserts natively via the
            // existing insert-pattern / insert-component commands. Light metadata
            // only; payloads are fetched on insert (repository stays authoritative).
            // Human labels resolved for the Admin UI locale (PB-TEMPLATE-REGISTRY-1-H3) — never the
            // editing locale. Machine identity (rid/kind/version/category/tags) stays invariant.
            'builderResources' => $this->builderResources?->toEditorArray(
                \Plugins\PageBuilder\Support\CatalogLocale::admin(),
                \Plugins\PageBuilder\Support\CatalogLocale::fallback(),
            ) ?? ['section' => [], 'block' => []],
        ];
    }

    /**
     * The editor field schema for a single section/block, or null when the kind
     * is unknown or the type is not registered.
     *
     * @param  'section'|'block'  $kind
     * @return array<string, mixed>|null
     */
    public function schemaFor(string $kind, string $type): ?array
    {
        $registry = match ($kind) {
            'section' => $this->sections,
            'block' => $this->blocks,
            default => null,
        };

        if ($registry === null) {
            return null;
        }

        $definition = $registry->get($type);

        return is_array($definition) ? ($definition['schema'] ?? null) : null;
    }

    /**
     * Whether the builder is enabled (config master switch).
     */
    public function isEnabled(): bool
    {
        return (bool) config('page-builder.enabled', true);
    }

    /**
     * A status snapshot for the admin dashboard and the read-only status API.
     *
     * @return array{
     *     name: string,
     *     version: string,
     *     enabled: bool,
     *     sections_count: int,
     *     blocks_count: int,
     *     sections: array<int, string>,
     *     blocks: array<int, string>
     * }
     */
    public function status(): array
    {
        return [
            'name' => 'Page Builder',
            'version' => self::VERSION,
            'enabled' => $this->isEnabled(),
            'sections_count' => $this->sections->count(),
            'blocks_count' => $this->blocks->count(),
            'sections' => $this->sections->types(),
            'blocks' => $this->blocks->types(),
        ];
    }
}
