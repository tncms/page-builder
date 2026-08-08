{{--
    Generic Page Builder section wrapper (Phase 3).

    Fallback used for any section type without its own view. $children is the
    already-rendered, trusted block HTML produced by LayoutRenderer, so it is
    echoed raw; $type / $data come from the normalized layout.
--}}
<section class="pb-section pb-section--{{ $type }}">
    {!! $children !!}
</section>
