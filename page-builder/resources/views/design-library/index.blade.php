{{--
    Design Library (Phase 26.43) — server-rendered admin UI.

    Consumes the Design Resource Repository (via DesignLibraryService) for data and
    the Runtime Preview route for previews. It renders NO resource HTML itself.
    JavaScript is optional: filtering works via the form's query parameters.

    @var array $cards       list of LibraryCard
    @var array $facets      { kinds, categories, vendors, tags }
    @var array $filters     normalized active filters
    @var int   $count       number of cards shown
    @var int   $total       total resources available
    @var string $scriptUrl  URL of the enhancement module
--}}
@php
    /** @var array|null $target */
    $target = $target ?? null;
    $csrfToken = $csrfToken ?? '';
    $importPlanUrl = $importPlanUrl ?? '';
    $importUrl = $importUrl ?? '';
    $packageScriptUrl = $packageScriptUrl ?? '';
    $packageExportUrl = $packageExportUrl ?? '';
    $packageValidateUrl = $packageValidateUrl ?? '';
    $packageImportUrl = $packageImportUrl ?? '';
    // Phase 26.47D — Page Template destination workflow (admin "Use This Template").
    $templateScriptUrl = $templateScriptUrl ?? '';
    $templateDestinationsUrl = $templateDestinationsUrl ?? '';
    $templateCreateUrl = $templateCreateUrl ?? '';
    $templateApplyExistingUrl = $templateApplyExistingUrl ?? '';
    // Phase 26.47B — Picker mode locks the library to one kind and hides admin-only
    // actions (kind switching, export, package import). Admin mode is the default.
    $isPicker = $isPicker ?? false;
    $pickerKind = $pickerKind ?? null;
    // Phase 26.50D — Community discovery. Present only on the Community tab; the default
    // admin browse experience renders identically when it is absent.
    $tab = $tab ?? null;
    $community = $community ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($csrfToken !== '')<meta name="csrf-token" content="{{ $csrfToken }}">@endif
    <title>Design Library</title>
    <style>
        :root {
            --dl-bg: #0f1115; --dl-surface: #171a21; --dl-line: #262b36;
            --dl-text: #e7eaf0; --dl-muted: #9aa4b2; --dl-accent: #6ea8fe; --dl-radius: 12px;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font: 15px/1.5 system-ui, sans-serif; background: var(--dl-bg); color: var(--dl-text); }
        .dl-shell { max-width: 1200px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
        .dl-head { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .dl-head h1 { font-size: clamp(1.5rem, 1rem + 2vw, 2.25rem); margin: 0; letter-spacing: -0.02em; }
        .dl-head__actions { display: flex; gap: .5rem; align-self: center; }
        .dl-sub { color: var(--dl-muted); margin: .35rem 0 .1rem; max-width: 60ch; }
        .dl-count { color: var(--dl-muted); font-size: .82rem; margin: 0; }
        .dl-filters { display: grid; grid-template-columns: 2fr repeat(5, 1fr) auto; gap: .6rem; margin: 1.5rem 0; }
        .dl-filters label { display: flex; flex-direction: column; font-size: .72rem; color: var(--dl-muted); gap: .25rem; text-transform: uppercase; letter-spacing: .04em; }
        .dl-filters input, .dl-filters select {
            background: var(--dl-surface); color: var(--dl-text); border: 1px solid var(--dl-line);
            border-radius: 8px; padding: .55rem .6rem; font: inherit;
        }
        .dl-filters .dl-actions { align-self: end; display: flex; gap: .5rem; }
        .dl-btn { border: 1px solid var(--dl-line); background: var(--dl-surface); color: var(--dl-text); border-radius: 8px; padding: .55rem .9rem; font: inherit; cursor: pointer; }
        .dl-btn--primary { background: var(--dl-accent); color: #0b1220; border-color: transparent; font-weight: 600; }
        .dl-btn:hover { border-color: var(--dl-accent); }
        .dl-fav-only { display: flex; align-items: center; gap: .4rem; align-self: end; color: var(--dl-muted); font-size: .8rem; }
        .dl-tabs { display: flex; flex-wrap: wrap; gap: .4rem; margin: 0 0 1.25rem; border-bottom: 1px solid var(--dl-line); padding-bottom: .6rem; }
        .dl-tab { text-decoration: none; color: var(--dl-muted); border: 1px solid transparent; border-radius: 999px; padding: .35rem .85rem; font-size: .85rem; }
        .dl-tab:hover { color: var(--dl-text); border-color: var(--dl-line); }
        .dl-tab--active { color: #0b1220; background: var(--dl-accent); border-color: transparent; font-weight: 600; }
        .dl-grid { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.25rem; }
        .dl-card { background: var(--dl-surface); border: 1px solid var(--dl-line); border-radius: var(--dl-radius); overflow: hidden; display: flex; flex-direction: column; }
        .dl-card:focus-within { border-color: var(--dl-accent); }
        .dl-thumb { position: relative; aspect-ratio: 16 / 10; background: #0b0d12; overflow: hidden; border-bottom: 1px solid var(--dl-line); }
        .dl-thumb iframe { position: absolute; inset: 0; width: 200%; height: 200%; transform: scale(.5); transform-origin: top left; border: 0; pointer-events: none; background: #fff; }
        .dl-thumb--empty { display: grid; place-items: center; color: var(--dl-muted); font-size: .8rem; aspect-ratio: 16 / 10; }
        .dl-card__body { padding: .85rem 1rem 1rem; display: flex; flex-direction: column; gap: .5rem; flex: 1; }
        .dl-card__title { margin: 0; font-size: 1rem; }
        .dl-badges { display: flex; flex-wrap: wrap; gap: .35rem; }
        .dl-badge { font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; padding: .15rem .45rem; border-radius: 999px; border: 1px solid var(--dl-line); color: var(--dl-muted); }
        .dl-badge--kind { color: var(--dl-accent); border-color: color-mix(in oklab, var(--dl-accent), transparent 60%); }
        .dl-badge--pro { color: #ffcf70; border-color: #6b5a25; }
        .dl-meta { color: var(--dl-muted); font-size: .8rem; margin-top: auto; }
        .dl-card__actions { display: flex; gap: .4rem; margin-top: .6rem; }
        .dl-card__actions .dl-btn { padding: .4rem .7rem; font-size: .85rem; }
        .dl-fav[aria-pressed="true"] { color: #ffb3c7; border-color: #7a3b4d; }
        .dl-empty { text-align: center; padding: 4rem 1rem; color: var(--dl-muted); }
        .dl-modal[hidden] { display: none; }
        .dl-modal { position: fixed; inset: 0; background: rgba(6,8,12,.72); display: grid; place-items: center; padding: 2rem; z-index: 50; }
        .dl-modal__panel { background: var(--dl-surface); border: 1px solid var(--dl-line); border-radius: var(--dl-radius); width: min(1000px, 100%); max-height: 90vh; display: flex; flex-direction: column; }
        .dl-modal__bar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .75rem 1rem; border-bottom: 1px solid var(--dl-line); }
        .dl-devices { display: flex; gap: .35rem; }
        .dl-devices [aria-pressed="true"] { background: var(--dl-accent); color: #0b1220; border-color: transparent; }
        .dl-modal__frame { flex: 1; min-height: 60vh; background: #fff; }
        .dl-modal__frame iframe { width: 100%; height: 100%; min-height: 60vh; border: 0; }
        @media (max-width: 720px) { .dl-filters { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>
<main class="dl-shell"
    @if ($target) data-owner-type="{{ $target['ownerType'] }}" data-owner-id="{{ $target['ownerId'] }}" @endif
    data-import-plan-url="{{ $importPlanUrl }}"
    data-import-url="{{ $importUrl }}"
    data-package-export-url="{{ $packageExportUrl }}"
    data-package-validate-url="{{ $packageValidateUrl }}"
    data-package-import-url="{{ $packageImportUrl }}"
    data-template-destinations-url="{{ $templateDestinationsUrl }}"
    data-template-create-url="{{ $templateCreateUrl }}"
    data-template-apply-existing-url="{{ $templateApplyExistingUrl }}">
    @include('page-builder::design-library.header', ['count' => $count, 'total' => $total, 'isPicker' => $isPicker, 'pickerKind' => $pickerKind])

    <form class="dl-filters" method="get" action="" role="search" aria-label="Filter design resources">
        <label>Search
            <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search resources…">
        </label>
        @if ($tab === 'community')
            {{-- Preserve the Community discovery context across filter submits (Phase 26.50D). --}}
            <input type="hidden" name="tab" value="community">
            @if (($filters['collection'] ?? null) !== null)<input type="hidden" name="collection" value="{{ $filters['collection'] }}">@endif
            @if (($filters['state'] ?? null) !== null)<input type="hidden" name="state" value="{{ $filters['state'] }}">@endif
            @if (($filters['sort'] ?? null) !== null)<input type="hidden" name="sort" value="{{ $filters['sort'] }}">@endif
        @endif
        @if ($isPicker)
            {{-- Picker mode is scoped to one kind; keep it fixed across filter submits. --}}
            <input type="hidden" name="kind" value="{{ $pickerKind }}">
            <input type="hidden" name="mode" value="picker">
        @else
            <label>Kind
                <select name="kind">
                    <option value="">All</option>
                    @foreach ($facets['kinds'] as $kind)
                        <option value="{{ $kind }}" @selected($filters['kind'] === $kind)>{{ ucfirst($kind) }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label>Category
            <select name="category">
                <option value="">All</option>
                @foreach ($facets['categories'] as $category)
                    <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ ucfirst($category) }}</option>
                @endforeach
            </select>
        </label>
        <label>Source
            <select name="source">
                <option value="">All</option>
                @foreach ($facets['sources'] as $source)
                    <option value="{{ $source }}" @selected($filters['source'] === $source)>{{ ucfirst($source) }}</option>
                @endforeach
            </select>
        </label>
        <label>Vendor
            <select name="vendor">
                <option value="">All</option>
                @foreach ($facets['vendors'] as $vendor)
                    <option value="{{ $vendor }}" @selected($filters['vendor'] === $vendor)>{{ $vendor }}</option>
                @endforeach
            </select>
        </label>
        <label>Tag
            <select name="tag">
                <option value="">All</option>
                @foreach ($facets['tags'] as $tag)
                    <option value="{{ $tag }}" @selected($filters['tag'] === $tag)>{{ $tag }}</option>
                @endforeach
            </select>
        </label>
        <div class="dl-actions">
            <label class="dl-fav-only">
                <input type="checkbox" name="favorites" value="1" @checked($filters['favorites'])> Favorites
            </label>
            <button type="submit" class="dl-btn dl-btn--primary">Apply</button>
        </div>
    </form>

    {{-- Discovery tabs — the platform's canonical DesignResource kinds (Phase 26.47A).
         Hidden in Picker mode, which is locked to the Builder's context kind (26.47B). --}}
    @unless ($isPicker)
        @include('page-builder::design-library.tabs', ['filters' => $filters, 'tab' => $tab])
    @endunless

    @isset ($community)
        @include('page-builder::design-library.discovery', ['community' => $community, 'filters' => $filters])
    @endisset

    @if ($count === 0)
        <div class="dl-empty" role="status">
            <p><strong>{{ tn_trans('No design resources match your filters.') }}</strong></p>
            <p>{{ tn_trans('Try clearing the search, choosing a different kind, or importing a design.') }}</p>
        </div>
    @else
        <ul class="dl-grid" role="list" data-design-library-grid>
            @foreach ($cards as $card)
                @include('page-builder::design-library.card', ['card' => $card])
            @endforeach
        </ul>
    @endif
</main>

<div class="dl-modal" data-design-library-modal role="dialog" aria-modal="true" aria-labelledby="dl-modal-title" hidden>
    <div class="dl-modal__panel">
        <div class="dl-modal__bar">
            <strong id="dl-modal-title">Preview</strong>
            <div class="dl-devices" role="group" aria-label="Preview device">
                <button type="button" class="dl-btn" data-device="desktop" aria-pressed="true">Desktop</button>
                <button type="button" class="dl-btn" data-device="tablet" aria-pressed="false">Tablet</button>
                <button type="button" class="dl-btn" data-device="mobile" aria-pressed="false">Mobile</button>
            </div>
            <button type="button" class="dl-btn" data-design-library-close aria-label="Close preview">✕</button>
        </div>
        <div class="dl-modal__frame">
            <iframe title="Resource preview" data-design-library-frame sandbox="allow-same-origin"></iframe>
        </div>
    </div>
</div>

<div class="dl-modal" data-design-library-import role="dialog" aria-modal="true" aria-labelledby="dl-import-title" hidden>
    <div class="dl-modal__panel" style="width: min(560px, 100%);">
        <div class="dl-modal__bar">
            <strong id="dl-import-title">Use Template</strong>
            <button type="button" class="dl-btn" data-design-library-import-close aria-label="Cancel">✕</button>
        </div>
        <div style="padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: .75rem;">
            <p data-import-summary aria-live="polite" style="margin: 0; color: var(--dl-muted);">Preparing preview…</p>
            <div data-import-warnings role="status" style="display: none; color: #ffcf70;"></div>
            <div data-import-blockers role="alert" style="display: none; color: #ff9aa8;"></div>
            <p style="margin: 0; font-size: .82rem; color: var(--dl-muted);">
                This replaces the current Builder <strong>draft</strong> only. Published content, SEO, slug and title are untouched, and you can keep editing afterwards.
            </p>
            <div style="display: flex; gap: .5rem; justify-content: flex-end;">
                <button type="button" class="dl-btn" data-design-library-import-close>Cancel</button>
                <button type="button" class="dl-btn dl-btn--primary" data-design-library-import-confirm disabled>Use Template</button>
            </div>
        </div>
    </div>
</div>

{{-- The package (.tncms-design) import dialog + script are admin-only; Picker mode
     hides them (a Builder selects existing resources, it does not manage packages). --}}
@unless ($isPicker)
    @include('page-builder::design-library.import-dialog')
    {{-- Phase 26.47D — "Use This Template" destination workflow (new/existing page or post). --}}
    @include('page-builder::design-library.destination-dialog')
@endunless

<script type="module" src="{{ $scriptUrl }}"></script>
@if ($packageScriptUrl !== '' && ! $isPicker)<script type="module" src="{{ $packageScriptUrl }}"></script>@endif
@if ($templateScriptUrl !== '' && ! $isPicker)<script type="module" src="{{ $templateScriptUrl }}"></script>@endif
</body>
</html>
