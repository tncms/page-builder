{{--
    FAQ section (Phase 4). An optional heading over a list of accordion-item
    blocks. Heading text is escaped; $children is trusted rendered block HTML.
--}}
<section class="pb-section pb-section--faq pb-faq">
    @php($heading = \Plugins\PageBuilder\Support\Localize::text($data['heading'] ?? ''))
    @if ($heading !== '')
        <h2 class="pb-faq__heading">{{ $heading }}</h2>
    @endif
    <div class="pb-faq__items">
        @if (trim($children) === '' && ($pbEditor ?? false))
            <div class="pb-placeholder">{{ __('Add an accordion item') }}</div>
        @else
            {!! $children !!}
        @endif
    </div>
</section>
