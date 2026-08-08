{{--
    My Designs — management surface inside the Design Library (Phase 26.49).

    Manages user-created Design Resources: metadata edit, archive/restore, soft
    delete, duplicate, and open-in-Builder (working copy). It never edits Builder
    content and never exposes the normalized payload, fingerprint, RID or
    compatibility — those are system-managed and immutable after capture. Previews
    reuse the Runtime Preview route; JavaScript is optional enhancement (filtering
    and sorting work via query parameters).

    @var array  $designs        management rows (see DesignResourceManager::list)
    @var array  $facets         { categories, tags, versions, visibilities }
    @var array  $filters        normalized active filters
    @var int    $count          rows shown
    @var int    $total          active (non-archived) design count
    @var int    $archivedTotal  archived design count
    @var array  $endpoints      action endpoint URLs
    @var array  $visibilities   allowed visibility values
    @var string $libraryUrl     back-to-library URL
    @var string $scriptUrl      management ES module URL
    @var string $csrfToken
--}}
@php
    $filters = $filters ?? [];
    $endpoints = $endpoints ?? [];
    $visibilities = $visibilities ?? ['private', 'unlisted'];
    $csrfToken = $csrfToken ?? '';
    $libraryUrl = $libraryUrl ?? url('page-builder/library');
    $showingArchived = ! empty($filters['archived']);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($csrfToken !== '')<meta name="csrf-token" content="{{ $csrfToken }}">@endif
    <title>{{ tn_trans('My Designs') }}</title>
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
        .dl-sub { color: var(--dl-muted); margin: .35rem 0 .1rem; max-width: 60ch; }
        .dl-count { color: var(--dl-muted); font-size: .82rem; margin: 0; }
        .dl-link { color: var(--dl-accent); text-decoration: none; font-size: .85rem; }
        .dl-link:hover { text-decoration: underline; }
        .dl-filters { display: grid; grid-template-columns: 2fr repeat(4, 1fr) auto; gap: .6rem; margin: 1.5rem 0; }
        .dl-filters label { display: flex; flex-direction: column; font-size: .72rem; color: var(--dl-muted); gap: .25rem; text-transform: uppercase; letter-spacing: .04em; }
        .dl-filters input, .dl-filters select {
            background: var(--dl-surface); color: var(--dl-text); border: 1px solid var(--dl-line);
            border-radius: 8px; padding: .55rem .6rem; font: inherit;
        }
        .dl-actions { align-self: end; display: flex; gap: .5rem; }
        .dl-btn { border: 1px solid var(--dl-line); background: var(--dl-surface); color: var(--dl-text); border-radius: 8px; padding: .55rem .9rem; font: inherit; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .dl-btn--primary { background: var(--dl-accent); color: #0b1220; border-color: transparent; font-weight: 600; }
        .dl-btn--danger { color: #ff9aa8; border-color: #7a3b4d; }
        .dl-btn:hover { border-color: var(--dl-accent); }
        .dl-btn[disabled] { opacity: .5; cursor: not-allowed; }
        .dl-tabs { display: flex; flex-wrap: wrap; gap: .4rem; margin: 0 0 1.25rem; border-bottom: 1px solid var(--dl-line); padding-bottom: .6rem; }
        .dl-tab { text-decoration: none; color: var(--dl-muted); border: 1px solid transparent; border-radius: 999px; padding: .35rem .85rem; font-size: .85rem; }
        .dl-tab:hover { color: var(--dl-text); border-color: var(--dl-line); }
        .dl-tab--active { color: #0b1220; background: var(--dl-accent); border-color: transparent; font-weight: 600; }
        .dl-grid { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.25rem; }
        .dl-card { background: var(--dl-surface); border: 1px solid var(--dl-line); border-radius: var(--dl-radius); overflow: hidden; display: flex; flex-direction: column; }
        .dl-card[data-archived="1"] { opacity: .72; }
        .dl-thumb { position: relative; aspect-ratio: 16 / 10; background: #0b0d12; overflow: hidden; border-bottom: 1px solid var(--dl-line); }
        .dl-thumb iframe { position: absolute; inset: 0; width: 200%; height: 200%; transform: scale(.5); transform-origin: top left; border: 0; pointer-events: none; background: #fff; }
        .dl-thumb--empty { display: grid; place-items: center; color: var(--dl-muted); font-size: .8rem; aspect-ratio: 16 / 10; }
        .dl-card__body { padding: .85rem 1rem 1rem; display: flex; flex-direction: column; gap: .5rem; flex: 1; }
        .dl-card__title { margin: 0; font-size: 1rem; }
        .dl-badges { display: flex; flex-wrap: wrap; gap: .35rem; }
        .dl-badge { font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; padding: .15rem .45rem; border-radius: 999px; border: 1px solid var(--dl-line); color: var(--dl-muted); }
        .dl-badge--kind { color: var(--dl-accent); border-color: color-mix(in oklab, var(--dl-accent), transparent 60%); }
        .dl-badge--archived { color: #ffcf70; border-color: #6b5a25; }
        .dl-meta { color: var(--dl-muted); font-size: .8rem; margin-top: auto; }
        .dl-card__actions { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .6rem; }
        .dl-card__actions .dl-btn { padding: .4rem .7rem; font-size: .82rem; }
        .dl-empty { text-align: center; padding: 4rem 1rem; color: var(--dl-muted); }
        .dl-modal[hidden] { display: none; }
        .dl-modal { position: fixed; inset: 0; background: rgba(6,8,12,.72); display: grid; place-items: center; padding: 2rem; z-index: 50; }
        .dl-modal__panel { background: var(--dl-surface); border: 1px solid var(--dl-line); border-radius: var(--dl-radius); width: min(560px, 100%); max-height: 90vh; overflow: auto; }
        .dl-modal__bar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .75rem 1rem; border-bottom: 1px solid var(--dl-line); }
        .dl-toast { position: fixed; bottom: 1.25rem; left: 50%; transform: translateX(-50%); background: var(--dl-surface); border: 1px solid var(--dl-line); border-radius: 8px; padding: .6rem 1rem; font-size: .85rem; z-index: 60; }
        .dl-toast[hidden] { display: none; }
        @media (max-width: 720px) { .dl-filters { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>
<main class="dl-shell"
    data-endpoint-metadata="{{ $endpoints['metadata'] ?? '' }}"
    data-endpoint-archive="{{ $endpoints['archive'] ?? '' }}"
    data-endpoint-restore="{{ $endpoints['restore'] ?? '' }}"
    data-endpoint-delete="{{ $endpoints['delete'] ?? '' }}"
    data-endpoint-duplicate="{{ $endpoints['duplicate'] ?? '' }}"
    data-endpoint-open="{{ $endpoints['open'] ?? '' }}">
    <header class="dl-head">
        <div>
            <h1>{{ tn_trans('My Designs') }}</h1>
            <p class="dl-sub">{{ tn_trans('Manage your saved designs: rename, categorise, duplicate, archive or open in the Builder. Saved designs are immutable — editing content creates a new design.') }}</p>
            <p class="dl-count" role="status">
                {{ $count }} {{ tn_trans('shown') }} · {{ $total }} {{ tn_trans('active') }} · {{ $archivedTotal }} {{ tn_trans('archived') }}
            </p>
        </div>
        <a class="dl-link" href="{{ $libraryUrl }}">← {{ tn_trans('Back to Design Library') }}</a>
    </header>

    <form class="dl-filters" method="get" action="" role="search" aria-label="{{ tn_trans('Filter my designs') }}">
        @if ($showingArchived)<input type="hidden" name="archived" value="1">@endif
        <label>{{ tn_trans('Search') }}
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ tn_trans('Search designs…') }}">
        </label>
        <label>{{ tn_trans('Type') }}
            <select name="kind">
                <option value="">{{ tn_trans('All') }}</option>
                @foreach (['section' => 'Sections', 'block' => 'Blocks'] as $k => $kLabel)
                    <option value="{{ $k }}" @selected(($filters['kind'] ?? null) === $k)>{{ tn_trans($kLabel) }}</option>
                @endforeach
            </select>
        </label>
        <label>{{ tn_trans('Category') }}
            <select name="category">
                <option value="">{{ tn_trans('All') }}</option>
                @foreach ($facets['categories'] as $category)
                    <option value="{{ $category }}" @selected(($filters['category'] ?? null) === $category)>{{ ucfirst($category) }}</option>
                @endforeach
            </select>
        </label>
        <label>{{ tn_trans('Visibility') }}
            <select name="visibility">
                <option value="">{{ tn_trans('All') }}</option>
                @foreach ($facets['visibilities'] as $vis)
                    <option value="{{ $vis }}" @selected(($filters['visibility'] ?? null) === $vis)>{{ ucfirst($vis) }}</option>
                @endforeach
            </select>
        </label>
        <label>{{ tn_trans('Sort') }}
            <select name="sort">
                @foreach (['newest' => 'Newest', 'oldest' => 'Oldest', 'updated' => 'Recently Updated', 'alphabetical' => 'Alphabetical'] as $s => $sLabel)
                    <option value="{{ $s }}" @selected(($filters['sort'] ?? 'newest') === $s)>{{ tn_trans($sLabel) }}</option>
                @endforeach
            </select>
        </label>
        <div class="dl-actions">
            <button type="submit" class="dl-btn dl-btn--primary">{{ tn_trans('Apply') }}</button>
        </div>
    </form>

    <nav class="dl-tabs" aria-label="{{ tn_trans('Design status') }}">
        <a class="dl-tab @unless ($showingArchived) dl-tab--active @endunless"
           @unless ($showingArchived) aria-current="page" @endunless
           href="{{ request()->fullUrlWithQuery(['archived' => null]) }}">{{ tn_trans('Active') }}</a>
        <a class="dl-tab @if ($showingArchived) dl-tab--active @endif"
           @if ($showingArchived) aria-current="page" @endif
           href="{{ request()->fullUrlWithQuery(['archived' => 1]) }}">{{ tn_trans('Archived') }}</a>
    </nav>

    @if ($count === 0)
        <div class="dl-empty" role="status">
            <p><strong>{{ tn_trans('No designs match.') }}</strong></p>
            <p>{{ $showingArchived ? tn_trans('You have no archived designs.') : tn_trans('Save a section or block from the Builder to see it here.') }}</p>
        </div>
    @else
        <ul class="dl-grid" role="list" data-my-designs-grid>
            @foreach ($designs as $design)
                <li class="dl-card" role="listitem" data-archived="{{ $design['archived'] ? '1' : '0' }}"
                    data-rid="{{ $design['rid'] }}"
                    data-name="{{ $design['name'] }}"
                    data-description="{{ $design['description'] ?? '' }}"
                    data-category="{{ $design['category'] }}"
                    data-tags="{{ implode(',', $design['tags']) }}"
                    data-visibility="{{ $design['visibility'] }}"
                    data-version-notes="{{ $design['versionNotes'] ?? '' }}"
                    data-can-manage="{{ $design['canManage'] ? '1' : '0' }}">
                    @if (! $design['archived'] && $design['previewUrl'] !== '')
                        <div class="dl-thumb">
                            <iframe src="{{ $design['previewUrl'] }}" title="{{ $design['name'] }}" loading="lazy" sandbox="allow-same-origin" tabindex="-1"></iframe>
                        </div>
                    @else
                        <div class="dl-thumb dl-thumb--empty">{{ $design['archived'] ? tn_trans('Archived') : tn_trans('No preview') }}</div>
                    @endif
                    <div class="dl-card__body">
                        <h2 class="dl-card__title">{{ $design['name'] }}</h2>
                        <div class="dl-badges">
                            <span class="dl-badge dl-badge--kind">{{ ucfirst($design['kind']) }}</span>
                            <span class="dl-badge">{{ ucfirst($design['category']) }}</span>
                            <span class="dl-badge">{{ ucfirst($design['visibility']) }}</span>
                            <span class="dl-badge">v{{ $design['version'] }}</span>
                            @if ($design['archived'])<span class="dl-badge dl-badge--archived">{{ tn_trans('Archived') }}</span>@endif
                        </div>
                        @if ($design['description'])<p class="dl-sub" style="font-size:.85rem;margin:0;">{{ $design['description'] }}</p>@endif
                        <p class="dl-meta">{{ tn_trans('Updated') }} {{ \Illuminate\Support\Str::limit((string) $design['updatedAt'], 10, '') }}</p>
                        @if ($design['canManage'])
                            <div class="dl-card__actions">
                                @unless ($design['archived'])
                                    <button type="button" class="dl-btn" data-action="edit">{{ tn_trans('Edit Metadata') }}</button>
                                    <button type="button" class="dl-btn" data-action="open">{{ tn_trans('Open in Builder') }}</button>
                                    <button type="button" class="dl-btn" data-action="duplicate">{{ tn_trans('Duplicate') }}</button>
                                    <button type="button" class="dl-btn" data-action="archive">{{ tn_trans('Archive') }}</button>
                                @else
                                    <button type="button" class="dl-btn" data-action="restore">{{ tn_trans('Restore') }}</button>
                                    <button type="button" class="dl-btn dl-btn--danger" data-action="delete">{{ tn_trans('Delete') }}</button>
                                @endunless
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</main>

@include('page-builder::design-library.metadata-dialog', ['visibilities' => $visibilities])
@include('page-builder::design-library.archive-dialog')

<div class="dl-toast" data-my-designs-toast role="status" aria-live="polite" hidden></div>

<script type="module" src="{{ $scriptUrl }}"></script>
</body>
</html>
