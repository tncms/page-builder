{{--
    A single Design Library card. @var \Plugins\PageBuilder\Library\LibraryCard $card

    Data attributes drive the enhancement script; the card is fully functional
    (metadata + a native lazy preview thumbnail) without JavaScript.
--}}
@php
    // Phase 26.47A/D — the primary action reads by resource kind: full pages "Use
    // Template", everything else is inserted into the current layout. "Import" is
    // reserved for package ingestion (Phase 26.47D frozen terminology).
    $insertLabel = match ($card->kind) {
        'page' => tn_trans('Use This Template'),
        'section' => tn_trans('Insert Section'),
        'block' => tn_trans('Insert Block'),
        'kit' => tn_trans('Insert Kit'),
        default => tn_trans('Insert'),
    };
    $insertVerb = $card->kind === 'page' ? tn_trans('Use') : tn_trans('Insert');
    // Phase 26.47B — in Picker mode the card offers Preview + the one context action
    // only; admin actions (Export) are hidden.
    $isPicker = $isPicker ?? false;
    // Phase 26.47D — an admin (non-picker) full-page card opens the destination
    // workflow (new/existing page or post). In Picker mode a page card applies to
    // the current Builder draft via the existing import flow.
    $usesDestinationWorkflow = $card->kind === 'page' && ! $isPicker;
@endphp
<li class="dl-card"
    data-design-library-card
    data-rid="{{ $card->rid }}"
    data-kind="{{ $card->kind }}"
    data-vendor="{{ $card->vendor }}"
    data-category="{{ $card->category }}"
    data-tags="{{ implode(' ', $card->tags) }}"
    data-name="{{ $card->name }}"
    data-preview-url="{{ $card->previewUrl }}"
    data-available="{{ $card->available ? '1' : '0' }}"
    data-locked="{{ $card->locked ? '1' : '0' }}">
    <div class="dl-thumb">
        @if ($card->available)
            <iframe
                title="Preview of {{ $card->name }}"
                loading="lazy"
                tabindex="-1"
                aria-hidden="true"
                sandbox="allow-same-origin"
                src="{{ $card->previewUrl }}"></iframe>
        @else
            <div class="dl-thumb--empty">Preview unavailable</div>
        @endif
    </div>
    <div class="dl-card__body">
        <h2 class="dl-card__title">{{ $card->name }}</h2>
        <div class="dl-badges">
            <span class="dl-badge dl-badge--kind">{{ $card->kind }}</span>
            <span class="dl-badge">{{ $card->category }}</span>
            @include('page-builder::design-library.availability-badge', ['card' => $card])
            @unless ($card->available)
                <span class="dl-badge">unavailable</span>
            @endunless
        </div>
        @foreach ($card->tags as $tag)
            <span class="dl-badge">#{{ $tag }}</span>
        @endforeach
        <p class="dl-meta">
            @if ($card->kind === 'page' && $card->sectionCount > 0)
                {{ $card->sectionCount }} {{ Str::plural('Section', $card->sectionCount) }} ·
            @endif
            {{ $card->vendor }} · v{{ $card->version }}
        </p>
        <div class="dl-card__actions">
            <button type="button" class="dl-btn dl-fav" data-design-library-favorite
                    aria-pressed="{{ $card->favorite ? 'true' : 'false' }}"
                    aria-label="Toggle favorite for {{ $card->name }}">★ Favorite</button>
            @if ($card->available)
                {{-- Preview is always offered for available resources — including
                     locked ones. Only the import/insert action is entitlement-gated. --}}
                <button type="button" class="dl-btn" data-design-library-preview
                        aria-label="Preview {{ $card->name }}">Preview</button>
                @unless ($card->locked || $isPicker)
                    {{-- Export the resource as a portable .tncms-design package (admin only). --}}
                    <button type="button" class="dl-btn" data-design-library-export
                            aria-label="Export {{ $card->name }}">Export</button>
                @endunless
                @if ($card->locked)
                    <button type="button" class="dl-btn" data-design-library-locked
                            disabled aria-disabled="true"
                            title="Requires {{ $card->requiredCapability }}"
                            aria-label="{{ $card->name }} requires an upgrade to insert">🔒 Locked</button>
                @elseif ($usesDestinationWorkflow)
                    <button type="button" class="dl-btn dl-btn--primary" data-design-library-use-template
                            aria-label="{{ $insertVerb }} {{ $card->name }}">{{ $insertLabel }}</button>
                @else
                    <button type="button" class="dl-btn dl-btn--primary" data-design-library-insert
                            aria-label="{{ $insertVerb }} {{ $card->name }}">{{ $insertLabel }}</button>
                @endif
            @endif
        </div>
    </div>
</li>
