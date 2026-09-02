<?php

declare(strict_types=1);

namespace Plugins\PageBuilder;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\ServiceProvider;
use Plugins\PageBuilder\Design\TokenRegistry;
use Plugins\PageBuilder\Design\TokenResolver;
use Plugins\PageBuilder\Filament\Forms\EditorLauncherPanel;
use Plugins\PageBuilder\Filament\Forms\PageRenderSourceSection;
use Plugins\PageBuilder\Http\Middleware\InjectFrontendEditorAssets;
use Plugins\PageBuilder\Http\Middleware\InjectPageBuilderAssets;
use Plugins\PageBuilder\LayoutTranslation\ContentLayoutOwnerLocator;
use Plugins\PageBuilder\LayoutTranslation\Contracts\LayoutOwnerLocatorInterface;
use Plugins\PageBuilder\LayoutTranslation\Contracts\LayoutTranslationCapabilityResolverInterface;
use Plugins\PageBuilder\LayoutTranslation\Contracts\LayoutTranslationStateReaderInterface;
use Plugins\PageBuilder\LayoutTranslation\DefaultLayoutTranslationCapabilityResolver;
use Plugins\PageBuilder\LayoutTranslation\DefaultLayoutTranslationStateReader;
use Plugins\PageBuilder\LayoutTranslation\LayoutRelationshipValidator;
use Plugins\PageBuilder\LayoutTranslation\LayoutTranslationConfig;
use Plugins\PageBuilder\LayoutTranslation\LayoutTranslationSetting;
use Plugins\PageBuilder\LayoutTranslation\LayoutTranslationDiagnostics;
use Plugins\PageBuilder\LayoutTranslation\LayoutTranslationResolver;
use TheNguyen\CMS\Localization\Contracts\LanguageConfigurationContract;
use Plugins\PageBuilder\Patterns\Dependencies\PatternCapabilityRegistry;
use Plugins\PageBuilder\Patterns\Dependencies\PatternDependencyResolver;
use Plugins\PageBuilder\Patterns\Migration\BuilderMigrator;
use Plugins\PageBuilder\Patterns\Migration\PatternMigrator;
use Plugins\PageBuilder\Patterns\Migration\Schema1To2Migration;
use Plugins\PageBuilder\Patterns\Packs\PatternPackLoader;
use Plugins\PageBuilder\Patterns\Packs\PatternPackRegistry;
use Plugins\PageBuilder\Patterns\PatternLoader;
use Plugins\PageBuilder\Patterns\PatternManager;
use Plugins\PageBuilder\Patterns\PatternRegistry;
use Plugins\PageBuilder\Patterns\PatternSourceResolver;
use Plugins\PageBuilder\Patterns\PatternValidator;
use Plugins\PageBuilder\Patterns\PatternVariableResolver;
use Plugins\PageBuilder\Patterns\Preview\GeneratedPreviewProvider;
use Plugins\PageBuilder\Patterns\Preview\PatternPreviewResolver;
use Plugins\PageBuilder\Patterns\Preview\PreviewProviderRegistry;
use Plugins\PageBuilder\Patterns\Preview\RenderPreviewProvider;
use Plugins\PageBuilder\Patterns\Screenshot\NullPatternScreenshotGenerator;
use Plugins\PageBuilder\Patterns\Screenshot\PatternScreenshotGenerator;
use Plugins\PageBuilder\Patterns\Support\PatternExporter;
use Plugins\PageBuilder\Patterns\Support\PatternImporter;
use Plugins\PageBuilder\Patterns\Support\PatternPackExporter;
use Plugins\PageBuilder\Patterns\Support\PatternPackImporter;
use Plugins\PageBuilder\Presets\ColumnLayoutPresetRegistry;
use Plugins\PageBuilder\Presets\CoreColumnLayoutPresetProvider;
use Plugins\PageBuilder\Registries\BlockRegistry;
use Plugins\PageBuilder\Registries\SectionRegistry;
use Plugins\PageBuilder\Runtime\ComponentFactory;
use Plugins\PageBuilder\Runtime\ComponentRuntime;
use Plugins\PageBuilder\Runtime\DesignTokenResolver;
use Plugins\PageBuilder\Runtime\RuntimeHookManager;
use Plugins\PageBuilder\Support\Breakpoints;
use Plugins\PageBuilder\Support\ColumnWidthRegistry;
use Plugins\PageBuilder\Support\DefaultLibrary;
use Plugins\PageBuilder\Support\DynamicStyle\DynamicStyleGenerator;
use Plugins\PageBuilder\Support\DynamicStyle\DynamicStyleHasher;
use Plugins\PageBuilder\Support\DynamicStyle\DynamicStyleNormalizer;
use Plugins\PageBuilder\Support\DynamicStyle\DynamicStyleRegistry;
use Plugins\PageBuilder\Support\EditableOwners;
use Plugins\PageBuilder\Support\FrontendEditorRenderer;
use Plugins\PageBuilder\Support\FrontendRenderer;
use Plugins\PageBuilder\Support\LayoutNormalizer;
use Plugins\PageBuilder\Support\ResponsiveSpacingStyle;
use Plugins\PageBuilder\Support\HeadingRankResolver;
use Plugins\PageBuilder\Support\CoreCompatibility;
use Plugins\PageBuilder\Support\LayoutRenderer;
use Plugins\PageBuilder\Support\LayoutStore;
use Plugins\PageBuilder\Support\PageBuilderManager;
use Plugins\PageBuilder\Exceptions\IncompatibleCoreException;
use TheNguyen\CMS\Models\Content;
use TheNguyen\CMS\Services\PluginInstallationGuard;

/**
 * Page Builder plugin service provider (Phase 1 — Foundation).
 *
 * Wires up only what a fresh page-builder needs: merged config, the two runtime
 * registries + the manager singleton (fronted by the PageBuilder facade), JSON
 * translations, and the plugin permissions. Migrations, the "page-builder::"
 * view namespace and web routes are loaded generically by the core
 * ExtensionManager, so they are not re-registered here.
 *
 * Additive and backward-compatible: nothing in TN CMS core is modified. The
 * Vue editor, drag-and-drop, layout persistence and the frontend renderer are
 * deliberately NOT part of this phase.
 */
class PageBuilderServiceProvider extends ServiceProvider
{
    /**
     * Test-only override of the compatibility-enforcement decision. `null`
     * (default) means "enforce in a real runtime, relax under the PHPUnit
     * harness" ({@see shouldEnforceCompatibility()}). A test sets `true` to
     * exercise the real provider path (throw + Core-skip on an incompatible
     * Core, or the accepted boot on a satisfied one) or `false` to force the
     * relaxed path, without ever touching Core. Reset to null in tearDown.
     *
     * @internal
     */
    private static ?bool $enforceCompatibilityOverride = null;

    /** @internal Test seam — see {@see $enforceCompatibilityOverride}. */
    public static function overrideCompatibilityEnforcement(?bool $enabled): void
    {
        self::$enforceCompatibilityOverride = $enabled;
    }

    /**
     * Plugin permissions (slug => human label). Registered in the permission
     * manager so they appear in the role editor and gate the admin dashboard.
     */
    private const PERMISSIONS = [
        'page-builder.view' => 'View Page Builder',
        'page-builder.edit' => 'Edit pages in Page Builder',
        'page-builder.publish' => 'Publish Page Builder layouts',
        'page-builder.settings' => 'Manage Page Builder settings',
        // LT-9C — read-only synchronization timeline permissions.
        'timeline.view' => 'View synchronization timeline',
        'timeline.replay' => 'Replay synchronizations (read-only)',
        'timeline.explain' => 'View synchronization explanations',
        // LT-9D — synchronization recovery workflow permissions (above the read-only timeline).
        'timeline.recovery.view' => 'View synchronization recovery',
        'timeline.recovery.plan' => 'Plan synchronization recovery',
        'timeline.recovery.execute' => 'Execute synchronization recovery',
        'timeline.recovery.override' => 'Override recovery conflicts',
    ];

    public function register(): void
    {
        // Core-compatibility fail-closed (PB RC-B). The heading contract depends
        // on the Core page-title seam (`show_page_title`) added in
        // 1.0.0-beta.7.1.23; on older Core it cannot be honoured. Core does NOT
        // enforce plugin.json `requires.tncms` (it is descriptive metadata), so
        // the plugin enforces it here. Throwing before the first binding means the
        // Core ExtensionManager — which registers this plugin's views, migrations
        // and routes only AFTER this register() and isolates each plugin — skips
        // the whole plugin: no bindings, hooks, assets, migrations or routes. See
        // shouldEnforceCompatibility() for why enforcement relaxes under the
        // isolated PHPUnit harness and how the real provider path stays testable.
        if (self::compatibilityBlocksBoot()) {
            $this->recordIncompatibility();

            throw new IncompatibleCoreException(CoreCompatibility::incompatibilityMessage());
        }

        $this->mergeConfigFrom(__DIR__.'/../config/page-builder.php', 'page-builder');

        // Registries are singletons so anything registered on boot (now or by
        // other plugins later) persists for the whole request.
        $this->app->singleton(SectionRegistry::class);
        $this->app->singleton(BlockRegistry::class);

        // Layout persistence gateway (Phase 2 — Layout Storage). Stateless over
        // the PageBuilderLayout model, so a singleton is safe and cheap.
        $this->app->singleton(LayoutStore::class);

        // PB-TREG-2 provenance schema authority: a signing key derived from APP_KEY
        // (never persisted) and the single reconciliation authority used by the save and
        // full-page-import lifecycles. A missing/invalid APP_KEY fails closed at resolve.
        $this->app->singleton(
            \Plugins\PageBuilder\Provenance\MaterializationAttestation::class,
            static fn (): \Plugins\PageBuilder\Provenance\MaterializationAttestation => \Plugins\PageBuilder\Provenance\MaterializationAttestation::fromAppKey(config('app.key')),
        );
        $this->app->singleton(\Plugins\PageBuilder\Provenance\ProvenanceReconciler::class);

        // Per-request collector of the arbitrary column widths used on a public
        // page. A singleton so the columns populate it during render and the head
        // asset injector reads the same instance (column-width parity hotfix).
        $this->app->singleton(ColumnWidthRegistry::class);

        // Generic Dynamic Style Runtime (Phase 26.2.1): the request-scoped
        // registry that turns safe Builder styling (padding/background/alignment/…)
        // into deduplicated `.pb-ds-*` head classes so it survives the theme output
        // sanitizer the same way column widths do. Singleton for the same reason:
        // blades populate it during render, the head injector reads it afterwards.
        $this->app->singleton(DynamicStyleNormalizer::class);
        $this->app->singleton(DynamicStyleHasher::class);
        $this->app->singleton(DynamicStyleGenerator::class);
        $this->app->singleton(DynamicStyleRegistry::class);

        // Normalization + lightweight server-side rendering (Phase 3). Both are
        // stateless; the renderer auto-resolves the normalizer via the container.
        $this->app->singleton(LayoutNormalizer::class);
        $this->app->singleton(LayoutRenderer::class);

        // Design tokens (Phase 6): a singleton seeded with the default token set,
        // plus a resolver that turns "group.name" references into their values.
        $this->app->singleton(TokenRegistry::class);
        $this->app->singleton(TokenResolver::class, function ($app): TokenResolver {
            return new TokenResolver($app->make(TokenRegistry::class));
        });

        // Component runtime (Phase 7): the lifecycle hook bus, the four resolvers
        // (design-token adapter wraps the Phase 6 resolver), the instance factory
        // and the runtime executor. All singletons so hook registrations persist.
        $this->app->singleton(RuntimeHookManager::class);
        $this->app->singleton(DesignTokenResolver::class, function ($app): DesignTokenResolver {
            return new DesignTokenResolver($app->make(TokenResolver::class));
        });
        $this->app->singleton(ComponentFactory::class);

        // PB-PROD-1: the breakpoint authority (thresholds read from the breakpoint
        // design tokens) and the responsive box-model spacing renderer, which emits
        // per-breakpoint overrides through the runtime style pipeline.
        $this->app->singleton(Breakpoints::class);
        $this->app->singleton(ResponsiveSpacingStyle::class);

        $this->app->singleton(ComponentRuntime::class);

        // Primary-heading authority (PB-FREE-LIBRARY-DESIGN-1-RC-B): one render-
        // scoped resolver coordinates the single document `<h1>` with the host
        // page-title visibility. Reset per render pass by the LayoutRenderer.
        $this->app->singleton(HeadingRankResolver::class);

        // Pattern Library (Phase 25): a data-driven layer on top of components.
        // Discovery/validation/lookup are singletons so the (lazy) discovery pass
        // is cached for the request. Paths are resolved, never hardcoded in code.
        $this->app->singleton(PatternLoader::class, function ($app): PatternLoader {
            return new PatternLoader($app->make(PatternMigrator::class), $app->make(BuilderMigrator::class));
        });
        $this->app->singleton(PatternValidator::class, function ($app): PatternValidator {
            return new PatternValidator($app->make(SectionRegistry::class), $app->make(BlockRegistry::class));
        });
        $this->app->singleton(PatternSourceResolver::class, function ($app): PatternSourceResolver {
            $config = (array) $app['config']->get('page-builder.patterns', []);

            return new PatternSourceResolver(
                corePatternsPath: dirname(__DIR__).'/resources/patterns',
                pluginsGlob: base_path('plugins/*/resources/page-builder/patterns'),
                themePatternsPath: $this->themePatternsPath($app),
                configSources: (array) ($config['sources'] ?? []),
            );
        });
        $this->app->singleton(PatternRegistry::class, function ($app): PatternRegistry {
            $config = (array) $app['config']->get('page-builder.patterns', []);

            return new PatternRegistry(
                $app->make(PatternLoader::class),
                $app->make(PatternValidator::class),
                categories: (array) ($config['categories'] ?? []),
                packLoader: $app->make(PatternPackLoader::class),
                packRegistry: $app->make(PatternPackRegistry::class),
            );
        });
        $this->app->singleton(PatternManager::class, function ($app): PatternManager {
            return new PatternManager($app->make(PatternRegistry::class), $app->make(PatternSourceResolver::class));
        });
        $this->app->singleton(PatternImporter::class, function ($app): PatternImporter {
            return new PatternImporter($app->make(PatternValidator::class), $app->make(PatternLoader::class));
        });
        $this->app->singleton(PatternExporter::class);

        // Phase 25.1 — variable resolution, preview strategy providers, and the
        // screenshot-generation seam. Preview providers register the image strategy
        // (the default) plus the render/generated architecture seams; the screenshot
        // generator is a no-op until a real (headless-browser) implementation lands.
        $this->app->singleton(PatternVariableResolver::class);
        $this->app->singleton(PreviewProviderRegistry::class, function (): PreviewProviderRegistry {
            return (new PreviewProviderRegistry)
                ->register(new RenderPreviewProvider)
                ->register(new GeneratedPreviewProvider);
        });
        $this->app->singleton(PatternPreviewResolver::class, function ($app): PatternPreviewResolver {
            return new PatternPreviewResolver($app->make(PreviewProviderRegistry::class));
        });
        $this->app->singleton(PatternScreenshotGenerator::class, NullPatternScreenshotGenerator::class);

        // Phase 25.2 — ecosystem hardening: schema migration pipeline, pack discovery,
        // and dependency resolution (plugins via the CMS extension manager, components
        // via the section/block registries, capabilities via an extensible registry).
        $this->app->singleton(PatternMigrator::class, function (): PatternMigrator {
            return (new PatternMigrator)->register(new Schema1To2Migration);
        });
        // Builder-schema migration seam (Phase 25.2.x, Part 3) — no migrations yet.
        $this->app->singleton(BuilderMigrator::class);
        $this->app->singleton(PatternPackLoader::class);
        $this->app->singleton(PatternPackRegistry::class);
        // Capabilities are config-driven (Phase 25.2.x, Part 2) — no hardcoded plugin
        // names; the environment declares the capabilities it supports.
        $this->app->singleton(PatternCapabilityRegistry::class, function (): PatternCapabilityRegistry {
            $registry = new PatternCapabilityRegistry;
            foreach ((array) config('page-builder.patterns.capabilities', []) as $capability) {
                if (is_string($capability)) {
                    $registry->register($capability);
                }
            }

            return $registry;
        });
        $this->app->singleton(PatternPackExporter::class, function ($app): PatternPackExporter {
            return new PatternPackExporter($app->make(PatternExporter::class));
        });
        $this->app->singleton(PatternPackImporter::class, function ($app): PatternPackImporter {
            return new PatternPackImporter($app->make(PatternImporter::class), $app->make(PatternMigrator::class));
        });
        $this->app->singleton(PatternDependencyResolver::class, function ($app): PatternDependencyResolver {
            return new PatternDependencyResolver(
                $app->make(SectionRegistry::class),
                $app->make(BlockRegistry::class),
                $app->make(PatternCapabilityRegistry::class),
                static function (string $plugin): bool {
                    $extension = app('cms.extension');

                    return method_exists($extension, 'isPluginActive') ? (bool) $extension->isPluginActive($plugin) : true;
                },
            );
        });

        // Column-layout preset providers (Phase 26.1.1). Seeded with the core
        // catalog; plugins/themes register their own providers on this singleton
        // during boot to contribute presets to the Layout popup.
        $this->app->singleton(ColumnLayoutPresetRegistry::class, function (): ColumnLayoutPresetRegistry {
            return (new ColumnLayoutPresetRegistry)->register(new CoreColumnLayoutPresetProvider);
        });

        $this->app->singleton('page-builder', function ($app): PageBuilderManager {
            return new PageBuilderManager(
                $app->make(SectionRegistry::class),
                $app->make(BlockRegistry::class),
                $app->make(LayoutStore::class),
                $app->make(LayoutRenderer::class),
                $app->make(TokenRegistry::class),
                $app->make(ComponentFactory::class),
                $app->make(ComponentRuntime::class),
                $app->make(ColumnLayoutPresetRegistry::class),
                // Phase 26.47E — the Builder Resource Registry feeds the editor
                // palette its grouped design-resource metadata.
                $app->make(\Plugins\PageBuilder\Builder\Registry\BuilderResourceRegistry::class),
            );
        });

        $this->app->alias('page-builder', PageBuilderManager::class);

        // Phase 26.41 — Runtime Preview engine. Read-only projection of a Design
        // Resource through the production render pipeline; no persistent storage.
        $this->app->singleton(\Plugins\PageBuilder\Preview\DevicePresets::class);
        $this->app->singleton(\Plugins\PageBuilder\Preview\SampleContentResolver::class);
        $this->app->singleton(\Plugins\PageBuilder\Preview\PreviewCache::class);
        $this->app->singleton(\Plugins\PageBuilder\Preview\PreviewResourceResolver::class);
        $this->app->singleton(\Plugins\PageBuilder\Preview\PreviewEngine::class);

        // Phase 26.42 — Design Resource Repository (single source of truth for
        // Design Resources). Providers register themselves in boot().
        $this->app->singleton(\Plugins\PageBuilder\Repository\Providers\LocalResourceProvider::class);
        $this->app->singleton(\Plugins\PageBuilder\Repository\Starters\StarterResourceCatalog::class);
        $this->app->singleton(\Plugins\PageBuilder\Repository\Providers\StarterResourceProvider::class);
        $this->app->singleton(\Plugins\PageBuilder\Repository\Providers\PackageResourceProvider::class);

        // Phase 26.47 — Design Package sharing (portable .tncms-design import/export).
        // The store is the only persistence; the imported provider serves resources
        // with source=imported through the repository.
        $this->app->singleton(\Plugins\PageBuilder\Package\ImportedPackageStore::class, static fn () => new \Plugins\PageBuilder\Package\ImportedPackageStore());
        $this->app->singleton(\Plugins\PageBuilder\Repository\Providers\ImportedResourceProvider::class);
        $this->app->singleton(\Plugins\PageBuilder\Package\DesignPackageExporter::class);
        $this->app->singleton(\Plugins\PageBuilder\Package\DesignPackageImporter::class);
        $this->app->singleton(\Plugins\PageBuilder\Repository\DesignResourceRepository::class);
        $this->app->singleton(
            \Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface::class,
            static fn ($app) => $app->make(\Plugins\PageBuilder\Repository\DesignResourceRepository::class),
        );

        // PB-TEMPLATE-REGISTRY-1-H1 — active-theme design-resource contribution. A
        // thin locator + reader + provider that pulls declarative patterns/templates
        // from ONLY the active theme (`themes/{active}/page-builder/...`) and feeds
        // them into the ONE repository through the frozen provider contract. The
        // themes root + active-slug resolver are constructor dependencies so a
        // Page Builder-owned test can inject an isolated fixture theme root without a
        // production config knob and without weakening path containment. Active theme
        // identity comes from the verified Core theme authority.
        $this->app->singleton(\Plugins\PageBuilder\Repository\Theme\ActiveThemeResourceLocator::class, static function ($app): \Plugins\PageBuilder\Repository\Theme\ActiveThemeResourceLocator {
            return new \Plugins\PageBuilder\Repository\Theme\ActiveThemeResourceLocator(
                base_path('themes'),
                static function () use ($app): ?string {
                    try {
                        if ($app->bound('cms.theme')) {
                            $manager = $app->make('cms.theme');
                            if (method_exists($manager, 'activeSlug')) {
                                $slug = $manager->activeSlug();
                                if (is_string($slug) && $slug !== '') {
                                    return $slug;
                                }
                            }
                        }
                    } catch (\Throwable) {
                        // Core theme authority unavailable (isolated contexts): fall through.
                    }

                    $slug = $app['config']->get('cms.theme')
                        ?? $app['config']->get('theme.active')
                        ?? $app['config']->get('cms.active_theme');

                    return is_string($slug) && $slug !== '' ? $slug : null;
                },
            );
        });
        $this->app->singleton(\Plugins\PageBuilder\Repository\Theme\ThemeResourceReader::class);
        $this->app->singleton(\Plugins\PageBuilder\Repository\Providers\ThemeResourceProvider::class);

        // Phase 26.44 — Full Page Template import (draft-only, atomic).
        $this->app->singleton(\Plugins\PageBuilder\Import\NodeIdGenerator::class);
        $this->app->singleton(\Plugins\PageBuilder\Import\FullPageImportService::class);

        // Phase 26.45 — FREE/PRO availability seam. The capability gate is the only
        // touch-point with authorization; the resolver reads distribution metadata
        // (C8) and never owns billing/marketplace logic.
        $this->app->bind(
            \Plugins\PageBuilder\Library\Availability\CapabilityGate::class,
            \Plugins\PageBuilder\Library\Availability\CmsCapabilityGate::class,
        );
        $this->app->singleton(\Plugins\PageBuilder\Library\Availability\ResourceAvailabilityResolver::class);

        // Phase 26.43 — Design Library UI service (consumes the repository only).
        $this->app->singleton(\Plugins\PageBuilder\Library\DesignLibraryService::class);
        $this->app->bind(
            \Plugins\PageBuilder\Library\FavoritesStoreInterface::class,
            static fn ($app) => new \Plugins\PageBuilder\Library\SessionFavoritesStore($app->make('session.store')),
        );

        // Phase 26.47E — Builder Resource Registry. ONE provider-based registry that
        // projects repository section/block resources into native palette metadata.
        // Providers read the authoritative repository; they never scan files. The
        // registry is the extensibility seam — plugins register their own provider.
        $this->app->singleton(\Plugins\PageBuilder\Builder\Registry\BuiltInProvider::class);
        $this->app->singleton(\Plugins\PageBuilder\Builder\Registry\ImportedProvider::class);
        // PB-TEMPLATE-REGISTRY-1-H2 — active-theme section/block resources in the palette.
        $this->app->singleton(\Plugins\PageBuilder\Builder\Registry\ThemeResourceBuilderProvider::class);
        $this->app->singleton(\Plugins\PageBuilder\Builder\Registry\BuilderResourceRegistry::class);

        // Phase 26.48 — Save as Design: the single Design Capture Pipeline + its
        // persistent "my-designs" store and repository provider. The pipeline is the
        // only writer into the my-designs source; the palette group stays reserved.
        $this->app->singleton(\Plugins\PageBuilder\Capture\UserDesignStore::class, static fn () => new \Plugins\PageBuilder\Capture\UserDesignStore());
        $this->app->singleton(\Plugins\PageBuilder\Repository\Providers\UserDesignProvider::class);
        $this->app->singleton(\Plugins\PageBuilder\Capture\DesignCaptureNormalizer::class);
        $this->app->singleton(\Plugins\PageBuilder\Capture\DesignCaptureValidator::class);
        $this->app->singleton(\Plugins\PageBuilder\Capture\DesignPreviewGenerator::class);
        $this->app->singleton(\Plugins\PageBuilder\Capture\DesignCapturePipeline::class);

        // Phase 26.49 — "My Designs" management lifecycle. Metadata-only edits plus
        // the archive / restore / soft-delete / duplicate / open-in-Builder actions
        // over the immutable captured resources. It reads the UserDesignStore
        // directly (so archived designs stay manageable) and never mutates payload,
        // fingerprint or RID; new content only enters through the Capture Pipeline.
        $this->app->singleton(\Plugins\PageBuilder\Management\OwnershipResolver::class);
        $this->app->singleton(\Plugins\PageBuilder\Management\MetadataUpdater::class);
        $this->app->singleton(\Plugins\PageBuilder\Management\ArchiveManager::class);
        $this->app->singleton(\Plugins\PageBuilder\Management\DuplicateManager::class);
        $this->app->singleton(\Plugins\PageBuilder\Management\DesignResourceManager::class);

        // Phase 26.50A — Community Repository foundation. Community is another
        // Provider built on the SDK; it reads a local, offline-ready manifest store
        // and contributes resources to the ONE repository. No marketplace, commerce,
        // sync or publishing — foundation only.
        $this->app->singleton(\Plugins\PageBuilder\Community\CommunityResourceStore::class, static fn () => new \Plugins\PageBuilder\Community\CommunityResourceStore());
        $this->app->singleton(\Plugins\PageBuilder\Community\Manifest\ManifestParser::class);
        $this->app->singleton(\Plugins\PageBuilder\Community\Manifest\ManifestValidator::class);
        $this->app->singleton(\Plugins\PageBuilder\Community\CommunityRepositoryProvider::class);

        // Phase 26.50B — Community publishing workflow. The lifecycle state machine,
        // the deterministic publishing pipeline and the workflow service that moves a
        // local draft to a published Community Resource. Workflow only — no sync,
        // upload, download, install or commerce.
        $this->app->singleton(\Plugins\PageBuilder\Community\Publishing\ResourceLifecycle::class);
        $this->app->singleton(\Plugins\PageBuilder\Community\Publishing\PublishingPipeline::class);
        $this->app->singleton(\Plugins\PageBuilder\Community\Publishing\CommunityPublishingService::class);

        // Phase 26.50C — Community synchronization protocol. The offline-ready cache and
        // the synchronization service that fetches a remote Repository Index, detects
        // change, reports conflicts and refreshes the local cache. Transport of metadata
        // only — no downloads, no payload transfer, no installation, no commerce.
        $this->app->singleton(\Plugins\PageBuilder\Community\Synchronization\SynchronizationCache::class, static fn () => new \Plugins\PageBuilder\Community\Synchronization\SynchronizationCache());
        $this->app->singleton(\Plugins\PageBuilder\Community\Synchronization\CommunitySynchronizationService::class);

        // Phase 26.50D — Community discovery & catalog. A presentation/indexing layer
        // over the ONE repository (never another repository): it overlays additive
        // catalog metadata (collections, featured states, sort weight) sourced from
        // config, and queries/filters/sorts/paginates. No accounts, ratings, reviews,
        // marketplace or commerce.
        $this->app->singleton(\Plugins\PageBuilder\Community\Discovery\CommunityDiscoveryService::class, static function ($app) {
            $catalog = (array) config('page-builder.design_library.catalog', []);

            return new \Plugins\PageBuilder\Community\Discovery\CommunityDiscoveryService(
                $app->make(\Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface::class),
                is_array($catalog['curation'] ?? null) ? $catalog['curation'] : [],
                is_array($catalog['collections'] ?? null) ? $catalog['collections'] : [],
            );
        });

        // Phase LT-1 — Layout Translation relationship foundation. Dormant while
        // the feature flag is off (the default): the resolver returns a descriptor
        // for the legacy shared layout for every locale, so single-language sites
        // are unchanged. Nothing here is wired into the frontend or editor yet — it
        // is a descriptor + resolver + validation + capability + diagnostics
        // foundation only. The canonical relationship is Translation Group → Page
        // Translation → Layout; locale authority is consumed from Core
        // ({@see LanguageConfigurationContract}); this plugin never owns it.
        $this->app->singleton(LayoutTranslationConfig::class, static function (): LayoutTranslationConfig {
            return new LayoutTranslationConfig((bool) config('page-builder.translation.enabled', false));
        });
        $this->app->bind(LayoutOwnerLocatorInterface::class, ContentLayoutOwnerLocator::class);
        $this->app->bind(LayoutTranslationCapabilityResolverInterface::class, DefaultLayoutTranslationCapabilityResolver::class);
        // The legacy pb_layouts-derived reader (LT-1) stays available as the
        // document-status source; LT-2 selects it or the persistent binding reader.
        $this->app->singleton(DefaultLayoutTranslationStateReader::class, static function ($app): DefaultLayoutTranslationStateReader {
            return new DefaultLayoutTranslationStateReader($app->make(LayoutStore::class));
        });
        // Frozen seam, flag-aware: OFF ⇒ legacy pb_layouts reader (byte-identical
        // legacy behaviour); ON ⇒ persistent binding-backed reader. The contract is
        // unchanged.
        $this->app->singleton(LayoutTranslationStateReaderInterface::class, static function ($app): LayoutTranslationStateReaderInterface {
            return (bool) config('page-builder.translation.enabled', false)
                ? $app->make(\Plugins\PageBuilder\LayoutTranslation\Persistence\PersistentLayoutTranslationStateReader::class)
                : $app->make(DefaultLayoutTranslationStateReader::class);
        });
        $this->app->singleton(LayoutTranslationResolver::class, static function ($app): LayoutTranslationResolver {
            return new LayoutTranslationResolver(
                $app->make(LayoutTranslationConfig::class),
                $app->make(LanguageConfigurationContract::class),
                $app->make(LayoutTranslationStateReaderInterface::class),
                $app->make(LayoutTranslationCapabilityResolverInterface::class),
            );
        });
        $this->app->singleton(LayoutRelationshipValidator::class, static function ($app): LayoutRelationshipValidator {
            return new LayoutRelationshipValidator($app->make(LayoutOwnerLocatorInterface::class));
        });
        $this->app->singleton(LayoutTranslationDiagnostics::class, static function ($app): LayoutTranslationDiagnostics {
            return new LayoutTranslationDiagnostics(
                $app->make(LayoutTranslationConfig::class),
                $app->make(LanguageConfigurationContract::class),
                $app->make(LayoutTranslationResolver::class),
                $app->make(LayoutRelationshipValidator::class),
            );
        });

        // Phase LT-2 — Persistent translation binding layer. Additive and dormant:
        // pb_layouts is untouched; the binding table owns only the relationship +
        // persisted status; nothing is wired into the frontend/editor. Repositories
        // return domain contracts / typed results, never Eloquent models. The
        // backfill is an explicit console command (never auto-run).
        $this->app->singleton(
            \Plugins\PageBuilder\LayoutTranslation\Persistence\Repositories\EloquentLayoutTranslationBindingRepository::class,
        );
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingRepositoryInterface::class,
            \Plugins\PageBuilder\LayoutTranslation\Persistence\Repositories\EloquentLayoutTranslationBindingRepository::class,
        );
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingReaderInterface::class,
            \Plugins\PageBuilder\LayoutTranslation\Persistence\Repositories\EloquentLayoutTranslationBindingRepository::class,
        );
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutDocumentLocatorInterface::class,
            \Plugins\PageBuilder\LayoutTranslation\Persistence\EloquentLayoutDocumentLocator::class,
        );
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Persistence\PersistentLayoutTranslationStateReader::class, static function ($app) {
            return new \Plugins\PageBuilder\LayoutTranslation\Persistence\PersistentLayoutTranslationStateReader(
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingReaderInterface::class),
                $app->make(DefaultLayoutTranslationStateReader::class),
            );
        });
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Persistence\LayoutTranslationBindingReconciler::class, static function ($app) {
            return new \Plugins\PageBuilder\LayoutTranslation\Persistence\LayoutTranslationBindingReconciler(
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingRepositoryInterface::class),
                $app->make(LanguageConfigurationContract::class),
                $app->make(DefaultLayoutTranslationStateReader::class),
            );
        });
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Persistence\LayoutTranslationBindingValidator::class, static function ($app) {
            return new \Plugins\PageBuilder\LayoutTranslation\Persistence\LayoutTranslationBindingValidator(
                $app->make(LayoutOwnerLocatorInterface::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutDocumentLocatorInterface::class),
            );
        });

        // Phase LT-3 — Layout Translation clone engine (Option D: generic Layout
        // Owner). Additive + flag-gated. Page Builder core owns the generic owner
        // abstraction; the Translation layer registers `content_translation` as one
        // owner. Clone advertises the `clone` capability (implementation-static, no
        // per-layout status). The clone service owns the atomic transaction.
        $this->app->singleton(\Plugins\PageBuilder\Ownership\LayoutOwnerRegistry::class);
        $this->app->bind(
            LayoutTranslationCapabilityResolverInterface::class,
            \Plugins\PageBuilder\LayoutTranslation\Cloning\Capability\CloneCapableCapabilityResolver::class,
        );
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Cloning\Owner\ContentTranslationOwnerResolver::class);
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TargetOwnerResolverInterface::class,
            \Plugins\PageBuilder\LayoutTranslation\Cloning\Owner\ContentTranslationOwnerResolver::class,
        );
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TransactionRunnerInterface::class,
            \Plugins\PageBuilder\LayoutTranslation\Cloning\Adapters\IlluminateTransactionRunner::class,
        );
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\SourceLayoutDocumentReaderInterface::class, static function ($app) {
            return new \Plugins\PageBuilder\LayoutTranslation\Cloning\Adapters\PageBuilderSourceLayoutDocumentReader(
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingReaderInterface::class),
                $app->make(LayoutStore::class),
                $app->make(LanguageConfigurationContract::class),
            );
        });
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TargetLayoutWriterInterface::class, static function ($app) {
            return new \Plugins\PageBuilder\LayoutTranslation\Cloning\Adapters\PageBuilderTargetLayoutWriter($app->make(LayoutStore::class));
        });
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Cloning\Eligibility\CloneEligibilityEvaluator::class, static function ($app) {
            return new \Plugins\PageBuilder\LayoutTranslation\Cloning\Eligibility\CloneEligibilityEvaluator(
                $app->make(LayoutTranslationConfig::class),
                $app->make(LayoutTranslationCapabilityResolverInterface::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Cloning\Owner\ContentTranslationOwnerResolver::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\SourceLayoutDocumentReaderInterface::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingReaderInterface::class),
                $app->make(LayoutStore::class),
                $app->make(\Plugins\PageBuilder\Ownership\LayoutOwnerRegistry::class),
            );
        });
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\LayoutTranslationCloneServiceInterface::class, static function ($app) {
            return new \Plugins\PageBuilder\LayoutTranslation\Cloning\Services\LayoutTranslationCloneService(
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Cloning\Eligibility\CloneEligibilityEvaluator::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Cloning\Owner\ContentTranslationOwnerResolver::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\SourceLayoutDocumentReaderInterface::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TargetLayoutWriterInterface::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingRepositoryInterface::class),
                $app->make(LayoutStore::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TransactionRunnerInterface::class),
            );
        });

        // Phase LT-4 — Baseline & structural correspondence. Additive + flag-gated.
        // Separate baseline store (the LT-2 binding table is untouched). Captures the
        // first authoritative provenance: a content-addressed source fingerprint plus
        // the target's node correspondence (`data['_origin']`). No diff, no sync.
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Baseline\Repositories\EloquentLayoutBaselineRepository::class);
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\Baseline\Contracts\LayoutBaselineRepositoryInterface::class,
            \Plugins\PageBuilder\LayoutTranslation\Baseline\Repositories\EloquentLayoutBaselineRepository::class,
        );
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Baseline\Services\LayoutBaselineCaptureService::class, static function ($app) {
            return new \Plugins\PageBuilder\LayoutTranslation\Baseline\Services\LayoutBaselineCaptureService(
                $app->make(LayoutTranslationConfig::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingReaderInterface::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\SourceLayoutDocumentReaderInterface::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Baseline\Contracts\LayoutBaselineRepositoryInterface::class),
                $app->make(LayoutStore::class),
                $app->make(LanguageConfigurationContract::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TransactionRunnerInterface::class),
            );
        });
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Baseline\LayoutBaselineDiagnostics::class, static function ($app) {
            return new \Plugins\PageBuilder\LayoutTranslation\Baseline\LayoutBaselineDiagnostics(
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Baseline\Contracts\LayoutBaselineRepositoryInterface::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingReaderInterface::class),
            );
        });

        // Phase LT-5 — Structural diff engine. Read-only, deterministic, payload-safe;
        // stateless (no dependencies). Computes the three-way structural diff
        // (baseline / source / target) by correspondence; it never mutates or
        // persists. Deciding what to do with the diff is LT-6.
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Diff\StructuralDiffEngine::class);

        // Phase LT-6 — Synchronization plan & conflict policy. Read-only, stateless,
        // fail-closed keep-target default. Turns a three-way diff into a reviewable
        // plan; it never mutates the target — execution is LT-7.
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationPlanner::class);

        // Phase LT-7 — Synchronization execution. The Coordinator owns the whole
        // workflow (load → provenance → mutate → persist → advance baseline → append
        // history) over the pure Mutation Engine, atomically and fail-closed. Additive
        // + flag-gated: nothing runs until page-builder.translation.enabled is true.
        //
        // Sync is now implemented, so the capability resolver advances to the LT-7
        // sync-capable set (clone + diff + sync). This is a NEW resolver — the frozen
        // default and LT-3 clone resolvers are untouched; Capability stays
        // implementation-static and status-free.
        $this->app->bind(
            LayoutTranslationCapabilityResolverInterface::class,
            \Plugins\PageBuilder\LayoutTranslation\Execution\Capability\SyncCapableCapabilityResolver::class,
        );
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\OperationApplierInterface::class,
            \Plugins\PageBuilder\LayoutTranslation\Execution\Mutation\DocumentOperationApplier::class,
        );
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\ExecutionEligibilityInterface::class,
            static function ($app) {
                return new \Plugins\PageBuilder\LayoutTranslation\Execution\Adapters\ConfigCapabilityExecutionEligibility(
                    $app->make(LayoutTranslationConfig::class),
                    $app->make(LayoutTranslationCapabilityResolverInterface::class),
                );
            },
        );
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\SynchronizationStateLoaderInterface::class,
            static function ($app) {
                return new \Plugins\PageBuilder\LayoutTranslation\Execution\Adapters\PageBuilderSynchronizationStateLoader(
                    $app->make(\Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingReaderInterface::class),
                    $app->make(\Plugins\PageBuilder\LayoutTranslation\Baseline\Contracts\LayoutBaselineRepositoryInterface::class),
                    $app->make(\Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\SourceLayoutDocumentReaderInterface::class),
                    $app->make(LayoutStore::class),
                    $app->make(LanguageConfigurationContract::class),
                );
            },
        );
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\TargetDraftWriterInterface::class,
            static function ($app) {
                return new \Plugins\PageBuilder\LayoutTranslation\Execution\Adapters\LayoutStoreTargetDraftWriter($app->make(LayoutStore::class));
            },
        );
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\BaselineAdvancerInterface::class,
            static function ($app) {
                return new \Plugins\PageBuilder\LayoutTranslation\Execution\Adapters\LayoutBaselineAdvancer(
                    $app->make(\Plugins\PageBuilder\LayoutTranslation\Baseline\Contracts\LayoutBaselineRepositoryInterface::class),
                );
            },
        );
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\SyncHistoryRecorderInterface::class,
            \Plugins\PageBuilder\LayoutTranslation\Execution\History\Repositories\EloquentSyncHistoryRepository::class,
        );
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\SynchronizationCoordinatorInterface::class, static function ($app) {
            return new \Plugins\PageBuilder\LayoutTranslation\Execution\Services\SynchronizationCoordinator(
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\ExecutionEligibilityInterface::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\SynchronizationStateLoaderInterface::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Execution\Validation\AcceptanceValidator::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Execution\Operations\OperationBuilder::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\OperationApplierInterface::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\TargetDraftWriterInterface::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\BaselineAdvancerInterface::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\SyncHistoryRecorderInterface::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TransactionRunnerInterface::class),
            );
        });
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Execution\Diagnostics\SynchronizationExecutionDiagnostics::class, static function ($app) {
            return new \Plugins\PageBuilder\LayoutTranslation\Execution\Diagnostics\SynchronizationExecutionDiagnostics(
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\ExecutionEligibilityInterface::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\SynchronizationStateLoaderInterface::class),
            );
        });

        // Phase LT-8D — Review Workspace runtime wiring. Binds the three LT-8 ports the frozen LT-8B
        // runtime + LT-8C presentation consume. Additive + flag-gated; no runtime, presentation,
        // coordinator, diff/planner, or persistence change.
        //
        // The baseline DOCUMENT is not persisted (LT-4 keeps a fingerprint only, Decision B2), so the
        // review provider reads it through the BaselineDocumentSource seam with a fail-closed default —
        // the workspace degrades to a fail-soft "unavailable" state until a later persistence phase
        // supplies baseline documents. Preview reads S/T lock-free; the session store is ephemeral (cache
        // only, no table/migration). ReviewApprovalService / ReviewWorkspaceInteractor / ReviewWorkspaceView
        // autowire from these bindings and the frozen coordinator.
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\ReviewInfrastructure\BaselineDocumentSourceInterface::class,
            \Plugins\PageBuilder\LayoutTranslation\ReviewInfrastructure\UnavailableBaselineDocumentSource::class,
        );
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\Review\Contracts\ReviewInputProviderInterface::class,
            static function ($app) {
                return new \Plugins\PageBuilder\LayoutTranslation\ReviewInfrastructure\PlanningReviewInputProvider(
                    $app->make(\Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\SynchronizationStateLoaderInterface::class),
                    $app->make(\Plugins\PageBuilder\LayoutTranslation\ReviewInfrastructure\BaselineDocumentSourceInterface::class),
                    $app->make(\Plugins\PageBuilder\LayoutTranslation\Diff\StructuralDiffEngine::class),
                    $app->make(\Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationPlanner::class),
                );
            },
        );
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\Review\Contracts\PreviewProjectionInterface::class,
            static function ($app) {
                return new \Plugins\PageBuilder\LayoutTranslation\ReviewInfrastructure\LayoutPreviewProjection(
                    $app->make(\Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\SynchronizationStateLoaderInterface::class),
                    $app->make(\Plugins\PageBuilder\LayoutTranslation\ReviewInfrastructure\BaselineDocumentSourceInterface::class),
                );
            },
        );
        $this->app->singleton(
            \Plugins\PageBuilder\LayoutTranslation\Review\Contracts\ReviewSessionStoreInterface::class,
            static function ($app) {
                return new \Plugins\PageBuilder\LayoutTranslation\ReviewInfrastructure\CacheReviewSessionStore(
                    $app->make(\Illuminate\Contracts\Cache\Repository::class),
                    (int) config('page-builder.translation.review_session_ttl', 3600),
                );
            },
        );

        // Phase LT-9C — Timeline presentation & infrastructure wiring. Read-only adapters + application
        // services connecting the FROZEN Timeline Runtime (LT-9B) to persistence and the admin UI. No
        // runtime/coordinator/planner/diff/history/snapshot change. The snapshot backend is fail-closed
        // (Null) until the LT-8.5 store is implemented, so replay degrades to Unavailable and explainability
        // to MetadataOnly — the honest fail-soft state.
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\Timeline\Contracts\SynchronizationHistoryReaderInterface::class,
            \Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure\EloquentTimelineHistoryReader::class,
        );
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure\TimelineSnapshotBackendInterface::class,
            \Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure\NullTimelineSnapshotBackend::class,
        );
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\Timeline\Contracts\TimelineSnapshotSourceInterface::class,
            static function ($app) {
                return new \Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure\SnapshotTimelineDocumentResolverAdapter(
                    $app->make(\Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure\TimelineSnapshotBackendInterface::class),
                );
            },
        );
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Timeline\Resolution\TimelineDocumentResolver::class, static function ($app) {
            return new \Plugins\PageBuilder\LayoutTranslation\Timeline\Resolution\TimelineDocumentResolver(
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Timeline\Contracts\TimelineSnapshotSourceInterface::class),
            );
        });
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Timeline\Builder\TimelineBuilder::class, static function ($app) {
            return new \Plugins\PageBuilder\LayoutTranslation\Timeline\Builder\TimelineBuilder(
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Timeline\Contracts\SynchronizationHistoryReaderInterface::class),
            );
        });
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Timeline\Replay\ReplayRuntime::class, static function ($app) {
            return new \Plugins\PageBuilder\LayoutTranslation\Timeline\Replay\ReplayRuntime(
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Timeline\Resolution\TimelineDocumentResolver::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Diff\StructuralDiffEngine::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationPlanner::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Execution\Operations\OperationBuilder::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\OperationApplierInterface::class),
            );
        });
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Timeline\Explainability\ExplainabilityRuntime::class, static function ($app) {
            return new \Plugins\PageBuilder\LayoutTranslation\Timeline\Explainability\ExplainabilityRuntime(
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Timeline\Resolution\TimelineDocumentResolver::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Diff\StructuralDiffEngine::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationPlanner::class),
            );
        });
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure\TimelineApplicationService::class, static function ($app) {
            return new \Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure\TimelineApplicationService(
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Timeline\Builder\TimelineBuilder::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Timeline\Replay\ReplayRuntime::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Timeline\Explainability\ExplainabilityRuntime::class),
            );
        });
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure\TimelineQueryService::class, static function ($app) {
            return new \Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure\TimelineQueryService(
                $app->make(\Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure\TimelineApplicationService::class),
            );
        });

        // Phase LT-9D — Synchronization Recovery Workflow. A framework-free orchestration layer ABOVE the
        // frozen Timeline Platform. It observes through the read seam, plans deterministically, and delegates
        // execution to the frozen Coordinator — it never mutates history, snapshots or baselines. The read
        // seam is a real read-only adapter over the runtime facades; the execution seam is fail-closed (the
        // command-building Coordinator adapter is a later phase), mirroring the Null snapshot backend.
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Recovery\RecoveryEligibilityEvaluator::class);
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Recovery\RecoveryConflictResolver::class);
        $this->app->singleton(
            \Plugins\PageBuilder\LayoutTranslation\Recovery\RecoveryPolicy::class,
            static fn () => \Plugins\PageBuilder\LayoutTranslation\Recovery\RecoveryPolicy::default(),
        );
        $this->app->singleton(
            \Plugins\PageBuilder\LayoutTranslation\Recovery\Contracts\RecoverySourceGatewayInterface::class,
            static function ($app) {
                return new \Plugins\PageBuilder\LayoutTranslation\Recovery\DefaultRecoverySourceGateway(
                    $app->make(\Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure\TimelineApplicationService::class),
                    $app->make(\Plugins\PageBuilder\LayoutTranslation\Timeline\Resolution\TimelineDocumentResolver::class),
                    $app->make(\Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\ExecutionEligibilityInterface::class),
                );
            },
        );
        $this->app->bind(
            \Plugins\PageBuilder\LayoutTranslation\Recovery\Contracts\RecoveryCoordinatorGatewayInterface::class,
            \Plugins\PageBuilder\LayoutTranslation\Recovery\UnavailableRecoveryCoordinatorGateway::class,
        );
        $this->app->singleton(\Plugins\PageBuilder\LayoutTranslation\Recovery\RecoveryWorkflow::class, static function ($app) {
            return new \Plugins\PageBuilder\LayoutTranslation\Recovery\RecoveryWorkflow(
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Recovery\Contracts\RecoverySourceGatewayInterface::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Recovery\RecoveryEligibilityEvaluator::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Recovery\RecoveryConflictResolver::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Recovery\Contracts\RecoveryCoordinatorGatewayInterface::class),
                $app->make(\Plugins\PageBuilder\LayoutTranslation\Recovery\RecoveryPolicy::class),
            );
        });
    }

    /**
     * Whether an incompatible Core must keep the whole plugin inert this boot.
     *
     * True = enforcement is on AND the running Core is below the minimum, so no
     * plugin surface (bindings, hooks, assets, migrations, and — via
     * routes/web.php which Core loads in a pass INDEPENDENT of the provider — the
     * plugin's routes) may register. Both the service provider ({@see register()})
     * and the plugin's route file consult this single decision so an unsupported
     * Core leaves no routable or bound surface behind.
     *
     * Enforcement defaults on in a real runtime but relaxes under the PHPUnit
     * harness, which force-loads the plugin against the pinned dev Core (older
     * than the minimum); relaxing there is safe because the packaged plugin never
     * runs under `runningUnitTests()`. The real path stays fully testable: a test
     * forces enforcement on via {@see overrideCompatibilityEnforcement()}, and the
     * disposable older/`.23` Core lifecycle certifications exercise it with no
     * override at all (where runningUnitTests() is already false).
     */
    public static function compatibilityBlocksBoot(): bool
    {
        $enforced = self::$enforceCompatibilityOverride ?? ! app()->runningUnitTests();

        return $enforced && ! CoreCompatibility::satisfied();
    }

    /**
     * Surface an incompatibility to administrators through the supported Core
     * plugin-error record ({@see PluginInstallationGuard}), the same store the
     * lifecycle manager uses for a failed install. Best-effort and idempotent:
     * guarded for a Core that predates the store, wrapped so a recording failure
     * never masks the compatibility error, and only written when the message
     * changes. The thrown exception (logged by the Core ExtensionManager with the
     * plugin slug) remains the authoritative signal.
     */
    private function recordIncompatibility(): void
    {
        try {
            if (! $this->app->bound('cms.plugin_installation_guard')) {
                return;
            }

            /** @var PluginInstallationGuard $guard */
            $guard = $this->app->make('cms.plugin_installation_guard');

            $message = CoreCompatibility::incompatibilityMessage();

            if ($guard->error('page-builder') !== $message) {
                $guard->recordError('page-builder', $message);
            }
        } catch (\Throwable) {
            // Recording is best-effort; the throw + Core boot log are authoritative.
        }
    }

    public function boot(): void
    {
        // Migrations, web routes and the "page-builder::" view namespace are
        // loaded by the ExtensionManager. Here we only add JSON translations
        // (en.json / vi.json) and the plugin permissions.
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');

        // LT-M1 — resolve the user-facing Layout Translation setting into the one
        // effective flag every runtime reader consumes (plugin setting + optional
        // env override + Auto(locale count)); no reader touches .env/settings.
        $this->applyEffectiveLayoutTranslationFlag();

        $this->registerPermissions();
        $this->registerDefaultLibrary();
        $this->registerResourceRepositoryProviders();
        $this->registerAdminFormIntegration();
        $this->registerFrontendRender();
        $this->registerFrontendEditorInjector();

        // LT-3 — register generic Layout Owner types: Page Builder's own content
        // owners, plus the Translation layer's `content_translation` owner. Page
        // Builder core never hardcodes translation; owners register here.
        $ownerRegistry = $this->app->make(\Plugins\PageBuilder\Ownership\LayoutOwnerRegistry::class);
        foreach (EditableOwners::types() as $ownerType) {
            $ownerRegistry->register($ownerType);
        }
        $ownerRegistry->register(\Plugins\PageBuilder\LayoutTranslation\Cloning\Owner\ContentTranslationOwner::OWNER_TYPE);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Plugins\PageBuilder\Console\MigrateLayoutCompositionCommand::class,
                \Plugins\PageBuilder\LayoutTranslation\Persistence\Console\BackfillLayoutTranslationBindingsCommand::class,
                \Plugins\PageBuilder\LayoutTranslation\Cloning\Console\CloneLayoutTranslationCommand::class,
            ]);
        }
    }

    /**
     * Resolve the effective Layout Translation flag and publish it as
     * config('page-builder.translation.enabled') so every reader (resolver,
     * renderer, controllers, projector) stays on that single key with no direct
     * env or settings lookup. Recomputed each boot, so an admin setting change
     * takes effect on the next request with no restart; the settings store owns
     * its own cache invalidation. Fail-soft: any storage/locale fault degrades to
     * the developer override (or legacy-off) instead of throwing during boot.
     */
    private function applyEffectiveLayoutTranslationFlag(): void
    {
        $override = LayoutTranslationSetting::normalizeOverride(
            config('page-builder.translation.override'),
        );

        $mode = LayoutTranslationSetting::MODE_AUTO;
        $enabledLocaleCount = 0;

        try {
            $mode = LayoutTranslationSetting::normalizeMode(
                settings(LayoutTranslationSetting::SETTING_KEY, LayoutTranslationSetting::MODE_AUTO),
            );
        } catch (\Throwable) {
            // Settings store unavailable (pre-install / migrating): keep Auto.
        }

        try {
            $enabledLocaleCount = count(
                $this->app->make(LanguageConfigurationContract::class)->enabledLocales(),
            );
        } catch (\Throwable) {
            // Core locale authority unavailable: Auto resolves to disabled (legacy).
        }

        config([
            'page-builder.translation.enabled' => LayoutTranslationSetting::decide(
                $override,
                $mode,
                $enabledLocaleCount,
            ),
        ]);
    }

    /**
     * Append the frontend-editor injector to the web middleware group so it can
     * place the iframe runtime into the FINAL html (after cms_html()). It is a
     * no-op on every request except an active, authorized editor render, so
     * public traffic is unaffected. `appendMiddlewareToGroup` re-syncs the router
     * even though it runs at boot.
     */
    /**
     * Best-effort active-theme pattern directory (§3). A `page-builder.patterns
     * .theme_path` config value wins; otherwise derive from a common active-theme
     * config key. Returns null when it can't be resolved — theme patterns are just
     * skipped, they never break discovery.
     */
    private function themePatternsPath(\Illuminate\Contracts\Foundation\Application $app): ?string
    {
        $override = $app['config']->get('page-builder.patterns.theme_path');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        $slug = $app['config']->get('cms.theme')
            ?? $app['config']->get('theme.active')
            ?? $app['config']->get('cms.active_theme');

        return is_string($slug) && $slug !== ''
            ? base_path('themes/'.$slug.'/page-builder/patterns')
            : null;
    }

    private function registerFrontendEditorInjector(): void
    {
        if (! $this->app->bound(HttpKernel::class)) {
            return;
        }

        $kernel = $this->app->make(HttpKernel::class);

        if (method_exists($kernel, 'appendMiddlewareToGroup')) {
            // Component stylesheet + tokens (public + editor iframe), then the
            // editor runtime (editor only). Both are no-ops unless the request
            // flagged/stashed the relevant payload.
            $kernel->appendMiddlewareToGroup('web', InjectPageBuilderAssets::class);
            $kernel->appendMiddlewareToGroup('web', InjectFrontendEditorAssets::class);
        }
    }

    /**
     * Render a page/post owner's published Page Builder layout on the public
     * frontend (Phase 14.3), through the core `cms.content.body` content filter.
     *
     * Purely additive: the plugin registers a filter the core already applies —
     * no core file is edited. Classic owners are untouched (the callback returns
     * the body unchanged), only `published_payload` is used, and the public
     * render path emits no editor markers or assets. Guarded so a missing hook
     * layer (isolated unit tests) degrades to a no-op.
     *
     * Phase 16 adds an editor branch: when the request carries the `pb_editor`
     * flag and the user holds `page-builder.edit`, {@see FrontendEditorRenderer}
     * takes over with the DRAFT payload + editor markers + iframe assets. That
     * branch is unreachable for a normal public visitor, so nothing changes for
     * them.
     */
    private function registerFrontendRender(): void
    {
        if (! function_exists('add_filter')) {
            return;
        }

        add_filter(
            'cms.content.body',
            static function (mixed $body, mixed $content = null): string {
                $body = is_string($body) ? $body : '';

                if (! $content instanceof Content) {
                    return $body;
                }

                // Frontend editor iframe: only when the request carries the
                // pb_editor flag AND the user holds page-builder.edit. Public
                // visitors always fall through to the published-only renderer.
                $editor = app(FrontendEditorRenderer::class);
                if ($editor->active($content)) {
                    // Returns a sanitizer-surviving placeholder and stashes the
                    // trusted canvas + assets for InjectFrontendEditorAssets to
                    // inject AFTER cms_html(), which would otherwise strip the
                    // canvas id, markers and scripts.
                    return $editor->registerForRequest($content, $body);
                }

                return app(FrontendRenderer::class)->bodyFor(
                    (string) $content->type,
                    $content->getKey(),
                    $body,
                    // Heading-context (PB-FREE-LIBRARY-DESIGN-1-E-H1): the generic
                    // core page-title visibility flag decides whether the theme
                    // renders the document <h1>, so the builder hero heading picks
                    // the correct rank. Defaults to Show (true) for legacy rows.
                    (bool) ($content->show_page_title ?? true),
                );
            },
            20,
            3,
        );
    }

    /**
     * Additively prepend the editor-mode switch (Classic Editor | UX Page
     * Builder) to the top of the Page and Post edit forms through the core
     * Admin Form Hook Bridge (cms.form.schema.*) — Phase 26.1.2.
     *
     * Using the SCHEMA filter (not the regions filter) places the segmented
     * control immediately beneath the page title so the Builder is discoverable
     * at once, instead of being buried at the bottom of the form. No app/core
     * resource is touched: the resources already thread their schema through
     * these filters. The panel only launches the reserved editor + reports
     * status; it never writes to the content record, so the Classic Editor keeps
     * working exactly as before. Guarded so a missing hook layer (e.g. in
     * isolated unit tests) degrades to a no-op.
     */
    private function registerAdminFormIntegration(): void
    {
        if (! function_exists('add_filter')) {
            return;
        }

        foreach (EditableOwners::types() as $ownerType) {
            add_filter(
                "cms.form.schema.{$ownerType}",
                static function (mixed $components) use ($ownerType): array {
                    $components = is_array($components) ? $components : [];
                    array_unshift($components, EditorLauncherPanel::make($ownerType));

                    return $components;
                },
                20,
                1,
            );
        }

        // PB-RS-1 (Page only) — the authoritative "Rendering" (Render Source)
        // control. Registered ONLY for `page`; Post/CPT are out of scope for this
        // phase (PB-RS-2 / PB-RS-3). Prepended after the launcher panel so it sits
        // just beneath it. See ADR-PB-RENDER-SOURCE-001.
        add_filter(
            'cms.form.schema.page',
            static function (mixed $components): array {
                $components = is_array($components) ? $components : [];
                array_unshift($components, PageRenderSourceSection::make());

                return $components;
            },
            21,
            1,
        );
    }

    /**
     * Populate the section/block registries with the built-in Phase 4 library.
     * Idempotent (registration overwrites by type) and additive; the actual
     * rendering resolves views by convention, so this only supplies editor
     * metadata + discoverability, never HTML or assets.
     */
    private function registerDefaultLibrary(): void
    {
        (new DefaultLibrary)->register(
            $this->app->make(SectionRegistry::class),
            $this->app->make(BlockRegistry::class),
        );
    }

    /**
     * Attach the built-in resource providers to the Design Resource Repository.
     * Registration is idempotent by provider key, so a repeated boot is safe.
     * Future providers (imported packages, cloud) register themselves the same way.
     */
    private function registerResourceRepositoryProviders(): void
    {
        $repository = $this->app->make(
            \Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface::class,
        );

        $repository->registerProvider(
            $this->app->make(\Plugins\PageBuilder\Repository\Providers\LocalResourceProvider::class),
        );
        // Phase 26.46 — FREE starter catalog (curated pages & sections).
        $repository->registerProvider(
            $this->app->make(\Plugins\PageBuilder\Repository\Providers\StarterResourceProvider::class),
        );
        $repository->registerProvider(
            $this->app->make(\Plugins\PageBuilder\Repository\Providers\PackageResourceProvider::class),
        );
        // Phase 26.47 — locally imported Design Packages (source=imported).
        $repository->registerProvider(
            $this->app->make(\Plugins\PageBuilder\Repository\Providers\ImportedResourceProvider::class),
        );
        // Phase 26.48 — captured "My Designs" (source=my-designs). Registered with the
        // repository so captured resources are immediately queryable; the dedicated
        // My Designs *palette* provider stays reserved (not added to the registry).
        $repository->registerProvider(
            $this->app->make(\Plugins\PageBuilder\Repository\Providers\UserDesignProvider::class),
        );

        // PB-TEMPLATE-REGISTRY-1-H1 — active-theme resources (source=theme). Disjoint
        // RIDs, content-derived revision; unavailable (contributes nothing) when no
        // theme is active or it ships no Page Builder resource files.
        $repository->registerProvider(
            $this->app->make(\Plugins\PageBuilder\Repository\Providers\ThemeResourceProvider::class),
        );

        // Phase 26.50A — Community Repository provider. Registered through the SDK's
        // compliance gate (soft, non-throwing): a compliant provider joins the ONE
        // repository via the documented seam; an empty store contributes nothing, so
        // behaviour is unchanged until Community resources are present.
        (new \Plugins\PageBuilder\SDK\ProviderRegistration)->registerIfCompliant(
            $repository,
            $this->app->make(\Plugins\PageBuilder\Community\CommunityRepositoryProvider::class),
        );

        // Phase 26.47E — attach the Builder Resource Registry providers (Built-in +
        // Imported). Idempotent by key; a third-party plugin registers its own
        // provider the same way without touching Builder code (Rule 14).
        $registry = $this->app->make(\Plugins\PageBuilder\Builder\Registry\BuilderResourceRegistry::class);
        $registry->registerProvider(
            $this->app->make(\Plugins\PageBuilder\Builder\Registry\BuiltInProvider::class),
        );
        $registry->registerProvider(
            $this->app->make(\Plugins\PageBuilder\Builder\Registry\ImportedProvider::class),
        );
        // PB-TEMPLATE-REGISTRY-1-H2 — active-theme resources join the palette through
        // the same provider seam (repository-backed, source=theme). Idempotent by key.
        $registry->registerProvider(
            $this->app->make(\Plugins\PageBuilder\Builder\Registry\ThemeResourceBuilderProvider::class),
        );
    }

    /**
     * Add the plugin permissions to the in-memory registry (so they surface in
     * the role editor) and persist them when running in the console — i.e. on
     * `plugin:activate` / `migrate`. Fully guarded: a missing permission manager
     * or an unready database degrades to a no-op rather than a boot crash.
     */
    private function registerPermissions(): void
    {
        if (! $this->app->bound('cms.permission')) {
            return;
        }

        try {
            $manager = $this->app->make('cms.permission');

            foreach (self::PERMISSIONS as $slug => $name) {
                $manager->register($slug, $name, 'Page Builder');
            }

            // Persist only in console (activation/migration), never per web
            // request, to avoid repeated writes on the hot path.
            if ($this->app->runningInConsole()) {
                $manager->syncDefaults();
            }
        } catch (\Throwable) {
            // Best-effort: the plugin still boots via the permission fail-open guard.
        }
    }
}
