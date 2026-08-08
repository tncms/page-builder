{{--
    Design Library discovery tabs (Phase 26.47A).

    The platform's canonical DesignResource kinds as primary discovery entries.
    They complement the Kind filter, preserve the other active filters, and drive
    server-side filtering (no JavaScript required).

    @var array $filters  normalized active filters (reads $filters['kind'])
    @var string|null $tab  active discovery surface ('community' when the Community tab is open)
--}}
<nav class="dl-tabs" aria-label="{{ tn_trans('Design resource kinds') }}">
    <a class="dl-tab @if ($filters['kind'] === null && ($tab ?? null) !== 'community') dl-tab--active @endif"
       @if ($filters['kind'] === null && ($tab ?? null) !== 'community') aria-current="page" @endif
       href="{{ request()->fullUrlWithQuery(['kind' => null, 'tab' => null, 'collection' => null, 'state' => null]) }}">{{ tn_trans('All Designs') }}</a>
    @foreach (['page' => 'Pages', 'section' => 'Sections', 'block' => 'Blocks', 'kit' => 'Kits'] as $tabKind => $tabLabel)
        <a class="dl-tab @if ($filters['kind'] === $tabKind) dl-tab--active @endif"
           @if ($filters['kind'] === $tabKind) aria-current="page" @endif
           href="{{ request()->fullUrlWithQuery(['kind' => $tabKind]) }}">{{ tn_trans($tabLabel) }}</a>
    @endforeach
    {{-- Phase 26.49 — "My Designs" management surface: user-created resources with
         their full lifecycle (metadata, archive, duplicate, delete, open in Builder).
         It lives inside the Design Library — never a separate library (Rule 1). --}}
    @php
        try { $myDesignsUrl = route('page-builder.library.my-designs'); }
        catch (\Throwable $e) { $myDesignsUrl = url('page-builder/library/my-designs'); }
    @endphp
    <a class="dl-tab" href="{{ $myDesignsUrl }}">{{ tn_trans('My Designs') }}</a>
    {{-- Phase 26.50D — Community discovery surface: collections, featured and
         compatibility filters over the community catalog. --}}
    <a class="dl-tab @if (($tab ?? null) === 'community') dl-tab--active @endif"
       @if (($tab ?? null) === 'community') aria-current="page" @endif
       href="{{ request()->fullUrlWithQuery(['tab' => 'community']) }}">{{ tn_trans('Community') }}</a>
</nav>
