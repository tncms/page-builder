{{--
    Hero section (Phase 4 / 15.1).

    A full-width intro band with its own editable title / subtitle / button
    (all translatable + escaped), followed by any trusted child blocks. So a
    freshly-added Hero shows content without requiring child blocks.
--}}
@php
    $title = \Plugins\PageBuilder\Support\Localize::text($data['title'] ?? '');
    $subtitle = \Plugins\PageBuilder\Support\Localize::text($data['subtitle'] ?? '');
    $buttonLabel = \Plugins\PageBuilder\Support\Localize::text($data['button_label'] ?? '');
    $buttonUrl = $data['button_url'] ?? null;
    $style = $data['style'] ?? 'plain';
    $width = $data['width'] ?? 'normal';
@endphp
<section class="pb-section pb-section--hero pb-hero pb-hero--{{ $style }} pb-align--{{ $data['align'] ?? 'center' }}">
    <div class="pb-hero__inner pb-container--{{ $width }}">
        @if ($title !== '')
            <h1 class="pb-hero__title">{{ $title }}</h1>
        @endif
        @if ($subtitle !== '')
            <p class="pb-hero__subtitle">{!! nl2br(e($subtitle)) !!}</p>
        @endif
        @if ($buttonLabel !== '')
            <a class="pb-hero__button pb-button pb-button--primary"
               href="{{ \Plugins\PageBuilder\Support\Html::safeUrl($buttonUrl) }}">{{ $buttonLabel }}</a>
        @endif
        {!! $children !!}
    </div>
</section>
