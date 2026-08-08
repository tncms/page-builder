{{--
    Page Builder — Vue editor shell (Phase 9).

    This Filament page hosts the Vue editor application. The heavy client mounts
    into #pb-editor-root; all of its data comes from window.TN_PAGE_BUILDER_BOOT
    (owner + layout + metadata + permissions + API endpoints), which mirrors the
    /editor/context endpoint exactly. The editor JS/CSS are referenced ONLY here
    (never globally, never on the public frontend). A no-JS fallback stays
    visible until Vue takes over.
--}}
@php
    use Plugins\PageBuilder\Support\EditableOwners;
    use Plugins\PageBuilder\Support\EditorContext;
    use Plugins\PageBuilder\Support\EditorUrls;

    /** @var \Plugins\PageBuilder\Models\PageBuilderLayout|null $layout */
    $owner = EditableOwners::resolve($ownerType, $ownerId);

    // LT-M1B Increment 2 — locale-aware boot (same runtime as the Frontend editor).
    // The context is built for the canonical page in the requested locale; the server
    // resolves the effective owner and (on first open) provisions the translated
    // layout. `$requestedLocale` / `$resolvedLocale` are supplied by the page mount,
    // which has already failed closed on a bad locale.
    $context = $owner ? app(EditorContext::class)->forCanonicalLocale($ownerType, $owner, $requestedLocale) : null;

    // Phase 22.2 — cross-mode navigation links + an optional selection to restore.
    // The resolved locale is carried across so cross-mode navigation stays in-locale.
    $nav = $owner
        ? EditorUrls::all($ownerType, $owner, null, $resolvedLocale)
        : ['structure' => null, 'live' => null, 'public' => url('/'), 'classic' => null];
    $selected = request()->query('selected');

    $version = \Plugins\PageBuilder\Support\PageBuilderManager::VERSION;
    $editorJs = asset('vendor/page-builder/editor/editor.js').'?v='.$version;
    $editorCss = asset('vendor/page-builder/editor/editor.css').'?v='.$version;

    // Build endpoint URLs from the configured API prefix so the boot payload
    // does not depend on named routes being registered when this view renders.
    $apiPrefix = trim((string) config('page-builder.api_prefix', 'api/page-builder'), '/');
    $ownerPath = $apiPrefix.'/editor/context/'.rawurlencode((string) $ownerType).'/'.rawurlencode((string) $ownerId);

    // Bake the resolved locale into the owner-scoped endpoints so context/draft/publish
    // resolve the SAME effective owner as this boot — a VI save writes the VI layout,
    // never the source (LT-M1B Inc2, §9). The backend remains authoritative: it
    // re-resolves the owner from the canonical page + this locale on every call.
    $localeQuery = $resolvedLocale !== '' ? '?locale='.rawurlencode($resolvedLocale) : '';

    $boot = [
        'mount' => '#pb-editor-root',
        'csrf' => csrf_token(),
        'locale' => $resolvedLocale !== '' ? $resolvedLocale : app()->getLocale(),
        'context' => $context,
        'exitUrl' => $context['owner']['edit_url'] ?? null,
        'nav' => $nav,
        'selected' => is_string($selected) ? $selected : null,
        'api' => [
            'context' => url($ownerPath.$localeQuery),
            'draft' => url($ownerPath.'/draft'.$localeQuery),
            'publish' => url($ownerPath.'/publish'.$localeQuery),
            'command' => url($apiPrefix.'/state/command'),
            'preview' => url($apiPrefix.'/editor/preview'),
            // Phase 26.47F — Runtime Preview route base for the resource Inspector
            // (reuses the existing 26.41 preview engine; no new endpoint).
            'resourcePreview' => url('page-builder/preview'),
        ],
    ];
@endphp

<x-filament-panels::page>
    <link rel="stylesheet" href="{{ $editorCss }}">

    <div
        id="pb-editor-root"
        class="pb-editor-root"
        data-owner-type="{{ $ownerType }}"
        data-owner-id="{{ $ownerId }}"
    >
        {{-- No-JS / pre-hydration fallback. Vue replaces this on mount. --}}
        <div class="pb-editor-fallback">
            <div class="pb-editor-fallback__title">{{ $ownerTitle }}</div>
            <p class="pb-editor-fallback__text">
                {{ __('Loading the Page Builder editor…') }}
            </p>
            <noscript>
                <p class="pb-editor-fallback__text">
                    {{ __('The Page Builder editor requires JavaScript. Please enable it, or use the Classic Editor.') }}
                </p>
            </noscript>
        </div>
    </div>

    <script>
        window.TN_PAGE_BUILDER_BOOT = @json($boot);
    </script>
    <script type="module" src="{{ $editorJs }}"></script>

    {{-- Phase 26.47B/D — Builder Design Resource picker. A sibling bridge to the Vue
         editor (no editor-bundle change): "Use Templates" opens the Design Library
         in Picker mode; the Builder owns every resulting operation. --}}
    @include('page-builder::builder.resource-picker.toolbar', ['ownerType' => $ownerType, 'ownerId' => $ownerId])
</x-filament-panels::page>
