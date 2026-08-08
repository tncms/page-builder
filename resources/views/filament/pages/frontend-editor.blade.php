{{--
    Page Builder — Frontend editor shell (Phase 16).

    Hosts the frontend-editor Vue shell. The shell mounts into
    #pb-frontend-editor-root and reads window.TN_PB_FRONTEND_EDITOR_BOOT (owner +
    layout context + metadata + permissions + API endpoints + the iframe URL of
    the owner's REAL public page). The shell bundle is referenced ONLY here —
    never globally, never on the public frontend. A no-JS fallback stays visible
    until Vue takes over.
--}}
@php
    use Plugins\PageBuilder\Filament\Pages\PageBuilderEditorPage;
    use Plugins\PageBuilder\Support\EditableOwners;
    use Plugins\PageBuilder\Support\EditorContext;
    use Plugins\PageBuilder\Support\EditorUrls;
    use Plugins\PageBuilder\Support\EditorAssets;

    $owner = EditableOwners::resolve($ownerType, $ownerId);

    // LT-M1B Increment 2 — locale-aware boot. The context is built for the canonical
    // page in the requested locale; the server resolves the effective owner and (on
    // first open) provisions the translated layout. `$requestedLocale` / `$resolvedLocale`
    // are supplied by the page mount, which has already failed closed on a bad locale.
    $context = $owner ? app(EditorContext::class)->forCanonicalLocale($ownerType, $owner, $requestedLocale) : null;

    // Per-file, filemtime-based cache busting (see EditorAssets) so a rebuilt
    // shell bundle is always re-fetched instead of served stale from cache.
    $shellJs = EditorAssets::url('vendor/page-builder/frontend-editor/shell.js');
    $shellCss = EditorAssets::url('vendor/page-builder/frontend-editor/shell.css');

    // A stale/partial build (e.g. a shell-only rebuild that orphaned iframe.js/css)
    // otherwise emits ?v=<VERSION> URLs that 404 and leave the bridge dead with no
    // explanation. Detect the missing bundles up front, log them, and render an
    // actionable error instead of a mysteriously broken editor.
    $missingAssets = EditorAssets::missing();
    if ($missingAssets !== []) {
        \Illuminate\Support\Facades\Log::warning('[page-builder] Live Builder assets missing — run `npm run build:frontend` in plugins/page-builder', [
            'missing' => $missingAssets,
        ]);
    }

    // The iframe loads the owner's real public page with the editor flag. The
    // FrontendEditorRenderer only honours that flag for a user with
    // page-builder.edit, so the draft render + editor assets stay private.
    //
    // LT-M1 — the base URL is built IN THE RESOLVED LOCALE so its prefix (e.g.
    // `/vi/...`) drives Core's ApplyPublicLocale for the WHOLE document (theme,
    // page title, header/menu, language control), not just the page-builder canvas
    // (which reads `?locale=` below). Falls back to the canonical URL when the
    // locale has no public URL yet, so the editor still opens.
    $publicUrl = $owner
        ? EditorUrls::publicUrl($owner, $resolvedLocale !== '' ? $resolvedLocale : null)
        : url('/');
    $sep = str_contains($publicUrl, '?') ? '&' : '?';
    // Carry the resolved locale into the iframe so the preview renderer resolves the
    // SAME effective owner as the editor (LT-M1B Inc2) — the VI iframe shows the VI
    // draft, not the source layout.
    $iframeUrl = $publicUrl.$sep.http_build_query([
        'pb_editor' => 1,
        'pb_owner_type' => $ownerType,
        'pb_owner_id' => $ownerId,
        'locale' => $resolvedLocale,
    ]);

    // Same-origin for postMessage targeting/validation.
    $frontendOrigin = ($scheme = parse_url($iframeUrl, PHP_URL_SCHEME))
        ? $scheme.'://'.parse_url($iframeUrl, PHP_URL_HOST).(($port = parse_url($iframeUrl, PHP_URL_PORT)) ? ':'.$port : '')
        : url('/');

    // Guarded: the structure-editor page route may be unregistered in isolated
    // tests; the shell simply hides the link when it can't be resolved.
    try {
        $structureEditUrl = PageBuilderEditorPage::getUrl(['ownerType' => $ownerType, 'ownerId' => $ownerId]);
    } catch (\Throwable) {
        $structureEditUrl = null;
    }

    // Phase 22.2 — cross-mode navigation links + an optional selection to restore.
    // The resolved locale is carried across so cross-mode navigation stays in-locale.
    $nav = $owner
        ? EditorUrls::all($ownerType, $owner, null, $resolvedLocale)
        : ['structure' => $structureEditUrl, 'live' => null, 'public' => $publicUrl, 'classic' => null];
    $selected = request()->query('selected');

    // Endpoint URLs from the configured API prefix (mirrors the structure editor).
    $apiPrefix = trim((string) config('page-builder.api_prefix', 'api/page-builder'), '/');
    $ownerPath = $apiPrefix.'/editor/context/'.rawurlencode((string) $ownerType).'/'.rawurlencode((string) $ownerId);

    // Bake the resolved locale into the owner-scoped endpoints so context/draft/publish
    // resolve the SAME effective owner as this boot — a VI save writes the VI layout,
    // never the source (LT-M1B Inc2, §9). The backend remains authoritative: it
    // re-resolves the owner from the canonical page + this locale on every call.
    $localeQuery = $resolvedLocale !== '' ? '?locale='.rawurlencode($resolvedLocale) : '';

    $boot = [
        'mount' => '#pb-frontend-editor-root',
        'csrf' => csrf_token(),
        'locale' => $resolvedLocale !== '' ? $resolvedLocale : app()->getLocale(),
        'context' => $context,
        'exitUrl' => $context['owner']['edit_url'] ?? null,
        'iframeUrl' => $iframeUrl,
        'frontendOrigin' => $frontendOrigin,
        'publicUrl' => $publicUrl,
        'structureEditUrl' => $structureEditUrl,
        'nav' => $nav,
        'selected' => is_string($selected) ? $selected : null,
        'api' => [
            'context' => url($ownerPath.$localeQuery),
            'draft' => url($ownerPath.'/draft'.$localeQuery),
            'publish' => url($ownerPath.'/publish'.$localeQuery),
            'command' => url($apiPrefix.'/state/command'),
            'preview' => url($apiPrefix.'/editor/preview'),
        ],
    ];
@endphp

<x-filament-panels::page>
    <link rel="stylesheet" href="{{ $shellCss }}">

    @if ($missingAssets !== [])
        {{-- Actionable build error: the Live Builder bundles are not published, so
             the editor cannot initialise. Shown instead of a silent broken bridge. --}}
        <div class="pb-frontend-editor-build-error" role="alert" style="margin:1rem 0;padding:1rem 1.25rem;border:1px solid #f59e0b;border-radius:.5rem;background:#fffbeb;color:#92400e;">
            <strong>{{ __('The Live Builder assets are not built.') }}</strong>
            <p style="margin:.5rem 0 0;">
                {{ __('Run the build to publish them, then reload:') }}
                <code>cd plugins/page-builder &amp;&amp; npm run build:frontend</code>
            </p>
            <ul style="margin:.5rem 0 0 1.25rem;list-style:disc;">
                @foreach ($missingAssets as $asset)
                    <li><code>{{ $asset }}</code></li>
                @endforeach
            </ul>
        </div>
    @endif

    <div
        id="pb-frontend-editor-root"
        class="pb-frontend-editor-root"
        data-owner-type="{{ $ownerType }}"
        data-owner-id="{{ $ownerId }}"
    >
        {{-- No-JS / pre-hydration fallback. Vue replaces this on mount. --}}
        <div class="pb-frontend-editor-fallback">
            <div class="pb-frontend-editor-fallback__title">{{ $ownerTitle }}</div>
            <p class="pb-frontend-editor-fallback__text">
                {{ __('Loading the frontend editor…') }}
            </p>
            <noscript>
                <p class="pb-frontend-editor-fallback__text">
                    {{ __('The frontend editor requires JavaScript. Please enable it, or use the Structure editor.') }}
                </p>
            </noscript>
        </div>
    </div>

    <script>
        window.TN_PB_FRONTEND_EDITOR_BOOT = @json($boot);
    </script>
    {{-- Vite IIFE bundle: loaded as a CLASSIC script (not type="module"). --}}
    <script src="{{ $shellJs }}"></script>
</x-filament-panels::page>
