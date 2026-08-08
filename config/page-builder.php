<?php

declare(strict_types=1);

/**
 * Page Builder plugin configuration (Phase 1 — Foundation).
 *
 * Deliberately small. The source of truth for a built page is a STRUCTURED
 * layout tree (sections → blocks), never raw HTML, so nothing here toggles an
 * HTML store. The Vue editor, drag-and-drop, layout persistence and the
 * frontend renderer are future phases and intentionally absent.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch surfaced on the admin status dashboard. When false the
    | plugin still boots (so the dashboard reports its own status) but future
    | phases should treat the builder as unavailable.
    |
    */
    'enabled' => (bool) env('PAGE_BUILDER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Route prefixes
    |--------------------------------------------------------------------------
    |
    | The admin editor is served by the Filament panel (see the dashboard
    | page). These prefixes scope the plugin's own raw route files:
    |   - api:   read-only status / future editor endpoints (api/page-builder/*)
    |   - admin: reserved seam for raw admin endpoints outside Filament
    |
    */
    'api_prefix' => 'api/page-builder',
    'admin_prefix' => 'page-builder',

    /*
    |--------------------------------------------------------------------------
    | Translation (LT-1)
    |--------------------------------------------------------------------------
    |
    | Shared namespace for every Page Builder translation feature (layout
    | translation, and later AI translation, review, translation memory,
    | synchronization) so configuration never fragments. DISABLED by default:
    | while off, every locale resolves the single shared layout exactly as today,
    | so a single-language site is unchanged and rollback stays config-only. The
    | LT-1 foundation (descriptor, resolver, validation, capabilities,
    | diagnostics) is dormant until enabled; cloning and synchronization are later
    | phases.
    |
    */
    'translation' => [
        // Optional developer / emergency override (NOT required for normal installs).
        // The user-facing switch is the "Layout Translation" setting in Page Builder
        // settings (cms_settings: page-builder.layout_translation). When present this
        // FORCES the feature regardless of that setting: true = on, false = off.
        // Absent (null) = defer to the admin setting. Document as an override only.
        'override' => env('PAGE_BUILDER_TRANSLATION'),

        // Effective runtime flag. Recomputed on every boot from override → admin
        // setting → Auto(enabled-locale count) by PageBuilderServiceProvider and
        // written back here, so every reader (resolver, renderer, controllers,
        // projector) keeps reading config('page-builder.translation.enabled') with
        // NO direct env or settings lookup. Do not set this by hand.
        'enabled' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Registries
    |--------------------------------------------------------------------------
    |
    | Empty in Phase 1. Sections and Blocks are registered at runtime through
    | the SectionRegistry / BlockRegistry (via the PageBuilder facade), not
    | listed here, so third-party plugins can extend the builder without
    | editing config.
    |
    */
    'sections' => [],
    'blocks' => [],

    /*
    |--------------------------------------------------------------------------
    | Pattern Library (Phase 25)
    |--------------------------------------------------------------------------
    |
    | Patterns are a data-driven, plugin/theme/marketplace-extensible layer on
    | top of the existing component system — each pattern is a manifest.json +
    | payload.json (the real Builder payload) + a preview image. Discovery scans
    | the sources below; on a duplicate id the HIGHEST priority wins
    | (theme > plugin packs > shipped core library). Paths are resolved by
    | PatternSourceResolver at runtime so nothing is hardcoded in the code paths.
    |
    */
    'patterns' => [
        // Extra absolute source paths (id => [path, priority]). The shipped core
        // library, the active theme, and other plugins are added automatically by
        // PatternSourceResolver; this is only for explicit/bespoke registrations.
        'sources' => [],

        // Capabilities the environment supports (Phase 25.2.x, Part 2). Patterns may
        // require capabilities (e.g. 'dynamic_content', 'advanced_animation'); a
        // capability NOT listed here is treated as missing. No hardcoded plugin names.
        'capabilities' => [],

        // Categories shown in the Elements panel (order controls display order).
        'categories' => [
            'hero' => ['name' => 'Hero', 'icon' => 'heroicon-o-rectangle-group', 'description' => 'Above-the-fold headers.', 'order' => 10],
            'features' => ['name' => 'Features', 'icon' => 'heroicon-o-squares-2x2', 'description' => 'Feature grids and highlights.', 'order' => 20],
            'content' => ['name' => 'Content', 'icon' => 'heroicon-o-document-text', 'description' => 'Rich content sections.', 'order' => 30],
            'cta' => ['name' => 'CTA', 'icon' => 'heroicon-o-megaphone', 'description' => 'Call-to-action blocks.', 'order' => 40],
            'pricing' => ['name' => 'Pricing', 'icon' => 'heroicon-o-currency-dollar', 'description' => 'Pricing tables.', 'order' => 50],
            'testimonials' => ['name' => 'Testimonials', 'icon' => 'heroicon-o-chat-bubble-left-right', 'description' => 'Social proof.', 'order' => 60],
            'faq' => ['name' => 'FAQ', 'icon' => 'heroicon-o-question-mark-circle', 'description' => 'Frequently asked questions.', 'order' => 70],
            'team' => ['name' => 'Team', 'icon' => 'heroicon-o-user-group', 'description' => 'Team member grids.', 'order' => 80],
            'logos' => ['name' => 'Logos', 'icon' => 'heroicon-o-building-office-2', 'description' => 'Logo strips.', 'order' => 90],
            'gallery' => ['name' => 'Gallery', 'icon' => 'heroicon-o-photo', 'description' => 'Image galleries.', 'order' => 100],
            'contact' => ['name' => 'Contact', 'icon' => 'heroicon-o-envelope', 'description' => 'Contact sections.', 'order' => 110],
            'footer' => ['name' => 'Footer', 'icon' => 'heroicon-o-bars-3-bottom-left', 'description' => 'Page footers.', 'order' => 120],
            'utility' => ['name' => 'Utility', 'icon' => 'heroicon-o-wrench-screwdriver', 'description' => 'Utility blocks.', 'order' => 130],
            'media' => ['name' => 'Media', 'icon' => 'heroicon-o-film', 'description' => 'Media-forward sections.', 'order' => 140],
            'layout' => ['name' => 'Layout', 'icon' => 'heroicon-o-view-columns', 'description' => 'Structural layouts.', 'order' => 150],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend assets
    |--------------------------------------------------------------------------
    |
    | Optional URLs to a lightweight frontend script / stylesheet the rendered
    | layout may need (e.g. a small progressive-enhancement bundle). NOT the Vue
    | editor — the frontend stays server-rendered. Null means "none", and
    | PageBuilder::assets() filters nulls out, so nothing is emitted by default.
    |
    */
    'assets' => [
        'js' => env('PAGE_BUILDER_ASSET_JS'),
        'css' => env('PAGE_BUILDER_ASSET_CSS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Runtime style debug
    |--------------------------------------------------------------------------
    |
    | When true, the Dynamic Style Runtime prefixes each generated `.pb-ds-*`
    | rule with a comment mapping the hashed class back to its properties
    | (e.g. `/* pb-ds-83f1ab → align-items, background, padding *​/`) so a
    | developer viewing source can trace a class to what it does. OFF by
    | default — production output stays comment-free and byte-for-byte identical.
    |
    */
    'runtime_style_debug' => (bool) env('PAGE_BUILDER_RUNTIME_STYLE_DEBUG', false),

];
