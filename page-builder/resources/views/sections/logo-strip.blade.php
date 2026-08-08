{{--
    Logo strip section (Phase 4). An optional heading over a row of logo blocks.
    Heading text is escaped; $children is trusted rendered block HTML.
--}}
<section class="pb-section pb-section--logo-strip pb-logo-strip {{ ($data['grayscale'] ?? true) ? 'pb-logo-strip--grayscale' : '' }}">
    @if (! empty($data['heading']))
        <p class="pb-logo-strip__heading">{{ $data['heading'] }}</p>
    @endif
    <div class="pb-logo-strip__items">
        @if (trim($children) === '' && ($pbEditor ?? false))
            <div class="pb-placeholder">{{ __('Add logos') }}</div>
        @else
            {!! $children !!}
        @endif
    </div>
</section>
