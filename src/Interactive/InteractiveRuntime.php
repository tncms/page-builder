<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Interactive;

use Plugins\PageBuilder\Components\ComponentAvailability;
use Plugins\PageBuilder\Components\ComponentProviderRegistry;
use Plugins\PageBuilder\Origins\ProviderOrigin;
use Throwable;

/**
 * Per-render collector and emitter for interactive component assets (PB-XREG-3, Interactive Runtime 1.0.0).
 *
 * The component runtime reports every rendered component type; only KNOWN types with a registered behavior that
 * supports the render context are kept. {@see plan()} turns the collected set into a deterministic, de-duplicated
 * asset list (Page Builder's runtime module first, then behaviors by id, each in dependency order). Public pages
 * emit it through Core's AssetRegistry (handle-keyed, topologically ordered, printed by the theme); the editor
 * receives it as an inert JSON manifest. A behavior whose files do not resolve is dropped as a whole (its static
 * markup stays) and reported by a bounded diagnostic code — never a path.
 */
class InteractiveRuntime
{
    public const RUNTIME_HANDLE = 'pbx-runtime';

    public const RUNTIME_PATH = 'interactive/runtime.js';

    public const OWNER = 'page-builder';

    public const ROOT_CLASS = 'pbx-root';

    /** type => behavior id. @var array<string, string> */
    private array $collected = [];

    /** @var array<int, array{code: string, behavior: string}> */
    private array $diagnostics = [];

    /** PB-XREG-4 — behavior id => origin kind of the planned behavior (asset source type only). @var array<string, string> */
    private array $behaviorOrigins = [];

    public function __construct(
        private readonly ComponentProviderRegistry $components,
        private readonly InteractiveAssetResolver $resolver,
    ) {}

    /** Start a fresh render scope (each public page / editor preview). */
    public function reset(): void
    {
        $this->collected = [];
        $this->diagnostics = [];
        $this->behaviorOrigins = [];
    }

    /**
     * The behavior that applies to a rendered component in `$context`, or null. Server-side registry lookup only:
     * a forged or unavailable `type` in layout JSON never yields a behavior.
     */
    public function behaviorForRender(string $type, string $context): ?InteractiveBehavior
    {
        $type = trim($type);
        if ($this->components->availability($type) !== ComponentAvailability::KNOWN) {
            return null;
        }

        $behavior = $this->components->behaviorFor($type);

        return $behavior !== null && $behavior->supports($context) ? $behavior : null;
    }

    /** Record that `$type` (with `$behavior`) was rendered in the current scope. */
    public function collect(string $type, InteractiveBehavior $behavior): void
    {
        $this->collected[trim($type)] = $behavior->id();
    }

    /** @return array<int, string> collected behavior ids, sorted, unique */
    public function collectedBehaviors(): array
    {
        $ids = array_values(array_unique($this->collected));
        sort($ids, SORT_STRING);

        return $ids;
    }

    /** The hydration-root class tokens for a behavior: `pbx-root pbx-b-{ns}--{local}`. @return array<int, string> */
    public static function rootClasses(InteractiveBehavior $behavior): array
    {
        return [self::ROOT_CLASS, self::behaviorClass($behavior->id())];
    }

    public static function behaviorClass(string $behaviorId): string
    {
        return 'pbx-b-'.str_replace(':', '--', $behaviorId);
    }

    /**
     * Deterministic asset plan for the collected behaviors.
     *
     * @return array<int, array{handle: string, kind: string, url: string, deps: array<int, string>, behavior: ?string}>
     */
    public function plan(string $context): array
    {
        $types = array_keys($this->collected);
        sort($types, SORT_STRING);

        $behaviors = [];
        foreach ($types as $type) {
            $behavior = $this->behaviorForRender($type, $context);
            if ($behavior !== null) {
                $behaviors[$behavior->id()] = [$behavior, (string) $this->components->ownerOf($type), $this->components->originOf($type)?->kind ?? ProviderOrigin::PLUGIN];
            }
        }
        ksort($behaviors, SORT_STRING);

        if ($behaviors === []) {
            return [];
        }

        $runtimeUrl = $this->resolver->url(self::OWNER, self::RUNTIME_PATH);
        if ($runtimeUrl === null) {
            $this->diagnose('interactive.runtime_unavailable', '*');

            return [];
        }

        $plan = [['handle' => self::RUNTIME_HANDLE, 'kind' => InteractiveAsset::KIND_MODULE, 'url' => $runtimeUrl, 'deps' => [], 'behavior' => null]];

        foreach ($behaviors as $id => [$behavior, $owner, $origin]) {
            $entries = [];
            foreach ($behavior->orderedAssets() as $asset) {
                $url = $owner === '' ? null : $this->resolver->url($owner, $asset->path(), $origin);
                if ($url === null) {
                    $this->diagnose('interactive.asset_unavailable', $id);
                    $entries = null;
                    break;
                }

                $deps = array_map(fn (string $d): string => self::handleFor($id, $d), $asset->deps());
                if ($asset->kind() === InteractiveAsset::KIND_MODULE) {
                    $deps[] = self::RUNTIME_HANDLE;
                }

                $entries[] = ['handle' => self::handleFor($id, $asset->handle()), 'kind' => $asset->kind(), 'url' => $url, 'deps' => $deps, 'behavior' => $id];
                $this->behaviorOrigins[$id] = $origin;
            }

            if ($entries !== null) {
                array_push($plan, ...$entries);
            }
        }

        return count($plan) > 1 ? $plan : [];
    }

    /** Core AssetRegistry handle for a behavior asset: `pbx.{ns}.{local}.{handle}`. */
    public static function handleFor(string $behaviorId, string $handle): string
    {
        return 'pbx.'.str_replace(':', '.', $behaviorId).'.'.$handle;
    }

    /**
     * Emit the public plan through Core's AssetRegistry (styles → head, modules → footer). Fail-soft: without
     * the registry nothing is emitted and the static markup remains fully usable.
     */
    public function enqueuePublic(): void
    {
        $plan = $this->plan(InteractiveBehavior::CONTEXT_PUBLIC);
        if ($plan === [] || ! app()->bound('cms.assets')) {
            return;
        }

        try {
            $assets = app('cms.assets');
            $kinds = array_column($plan, 'kind', 'handle');
            foreach ($plan as $entry) {
                // Core orders within its head/footer bucket; a cross-bucket dependency (module → stylesheet) would
                // pull the stylesheet into the footer a second time. Same-kind deps only — the plan already
                // guarantees cross-kind order (styles in <head> precede every footer module).
                $entry['deps'] = array_values(array_filter($entry['deps'], fn (string $d): bool => ($kinds[$d] ?? null) === $entry['kind']));
                $source = ['source_type' => ($this->behaviorOrigins[(string) $entry['behavior']] ?? null) === ProviderOrigin::THEME ? 'theme' : 'plugin', 'source_name' => $entry['behavior'] === null ? self::OWNER : strtok($entry['behavior'], ':')];
                if ($entry['kind'] === InteractiveAsset::KIND_CSS) {
                    $assets->registerStyle($entry['handle'], $entry['url'], $entry['deps'], null, 'frontend', 'head', [], null, $source);
                    $assets->enqueueStyle($entry['handle']);
                } else {
                    $assets->registerModule($entry['handle'], $entry['url'], $entry['deps'], null, 'frontend', 'footer', [], $source);
                    $assets->enqueueModule($entry['handle']);
                }
            }
        } catch (Throwable) {
            $this->diagnose('interactive.emit_failed', '*');
        }
    }

    /**
     * The inert editor manifest: the behaviors present in this render and the assets to load before mounting.
     *
     * @return array{behaviors: array<int, string>, assets: array<int, array{handle: string, kind: string, url: string}>}
     */
    public function editorManifest(): array
    {
        $plan = $this->plan(InteractiveBehavior::CONTEXT_EDITOR);
        $behaviors = array_values(array_unique(array_filter(array_map(fn (array $e) => $e['behavior'], $plan))));

        return [
            'behaviors' => $behaviors,
            'assets' => array_map(fn (array $e): array => ['handle' => $e['handle'], 'kind' => $e['kind'], 'url' => $e['url']], $plan),
        ];
    }

    /** @return array<int, array{code: string, behavior: string}> drained bounded diagnostics */
    public function drainDiagnostics(): array
    {
        $out = array_values(array_unique($this->diagnostics, SORT_REGULAR));
        $this->diagnostics = [];

        return $out;
    }

    private function diagnose(string $code, string $behavior): void
    {
        $this->diagnostics[] = ['code' => $code, 'behavior' => $behavior];
    }
}
