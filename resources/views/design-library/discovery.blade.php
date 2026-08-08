{{--
    Community Discovery panel (Phase 26.50D) — collections, featured resources, curated
    states and sort over the community catalog. Server-rendered, progressive-enhancement:
    every control is a plain GET link/select, so discovery works with no JavaScript.
    Preview, Insert, Use Template and Availability are unchanged (they render on the
    cards below via the shared card partial).

    @var array $community  { collections, featured (LibraryCard[]), categories, states, active }
    @var array $filters    normalized active filters
--}}
@php
    $active = $community['active'] ?? ['collection' => null, 'state' => null, 'sort' => 'featured'];
    $stateLabels = ['featured' => 'Featured', 'recommended' => 'Recommended', 'new' => 'New', 'updated' => 'Updated'];
    $sortLabels = ['featured' => 'Featured', 'newest' => 'Newest', 'updated' => 'Updated', 'name' => 'Name', 'category' => 'Category'];
@endphp
<section class="dl-discovery" aria-label="{{ tn_trans('Community discovery') }}" style="margin: 0 0 1.5rem;">
    @if (! empty($community['collections']))
        <div class="dl-discovery__group" style="margin-bottom: 1rem;">
            <h2 style="font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; color: var(--dl-muted); margin: 0 0 .5rem;">{{ tn_trans('Collections') }}</h2>
            <nav class="dl-tabs" aria-label="{{ tn_trans('Collections') }}" style="border-bottom: 0; padding-bottom: 0;">
                <a class="dl-tab @if (($active['collection'] ?? null) === null) dl-tab--active @endif"
                   href="{{ request()->fullUrlWithQuery(['tab' => 'community', 'collection' => null]) }}">{{ tn_trans('All') }}</a>
                @foreach ($community['collections'] as $collection)
                    <a class="dl-tab @if (($active['collection'] ?? null) === $collection['key']) dl-tab--active @endif"
                       href="{{ request()->fullUrlWithQuery(['tab' => 'community', 'collection' => $collection['key']]) }}"
                       @if ($collection['description'] ?? null) title="{{ $collection['description'] }}" @endif>{{ $collection['title'] }}</a>
                @endforeach
            </nav>
        </div>
    @endif

    <div class="dl-discovery__controls" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center;">
        <nav class="dl-tabs" aria-label="{{ tn_trans('Curated states') }}" style="border-bottom: 0; padding-bottom: 0;">
            <a class="dl-tab @if (($active['state'] ?? null) === null) dl-tab--active @endif"
               href="{{ request()->fullUrlWithQuery(['tab' => 'community', 'state' => null]) }}">{{ tn_trans('All') }}</a>
            @foreach (($community['states'] ?? []) as $state)
                <a class="dl-tab @if (($active['state'] ?? null) === $state) dl-tab--active @endif"
                   href="{{ request()->fullUrlWithQuery(['tab' => 'community', 'state' => $state]) }}">{{ tn_trans($stateLabels[$state] ?? ucfirst($state)) }}</a>
            @endforeach
        </nav>

        <form method="get" action="" style="margin-left: auto; display: flex; align-items: end; gap: .5rem;">
            <input type="hidden" name="tab" value="community">
            @if (($active['collection'] ?? null) !== null)<input type="hidden" name="collection" value="{{ $active['collection'] }}">@endif
            @if (($active['state'] ?? null) !== null)<input type="hidden" name="state" value="{{ $active['state'] }}">@endif
            <label style="display: flex; flex-direction: column; font-size: .72rem; color: var(--dl-muted); gap: .25rem; text-transform: uppercase; letter-spacing: .04em;">{{ tn_trans('Sort') }}
                <select name="sort" onchange="this.form.submit()">
                    @foreach ($sortLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($active['sort'] ?? 'featured') === $value)>{{ tn_trans($label) }}</option>
                    @endforeach
                </select>
            </label>
        </form>
    </div>

    @if (! empty($community['featured']) && ($active['collection'] ?? null) === null && ($active['state'] ?? null) === null)
        <div class="dl-discovery__featured" style="margin-top: 1.25rem;">
            <h2 style="font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; color: var(--dl-muted); margin: 0 0 .5rem;">{{ tn_trans('Featured') }}</h2>
            <ul class="dl-grid" role="list">
                @foreach ($community['featured'] as $card)
                    @include('page-builder::design-library.card', ['card' => $card])
                @endforeach
            </ul>
        </div>
    @endif
</section>
