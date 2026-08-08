{{--
    Image section (Phase 4 / 15.1). Renders its own image (scheme-sanitised URL,
    escaped alt/caption) when set, plus any trusted child image blocks. Renders
    no <img> without a URL (no broken image).
--}}
@php
    $src = \Plugins\PageBuilder\Support\Html::safeUrl($data['image_url'] ?? null, '');
    $alt = \Plugins\PageBuilder\Support\Localize::text($data['alt'] ?? '');
    $caption = \Plugins\PageBuilder\Support\Localize::text($data['caption'] ?? '');
@endphp
@php
    $ratio = $data['ratio'] ?? null;
    $sectionClasses = 'pb-image-section--'.($data['style'] ?? 'plain');
    if ($ratio) {
        $sectionClasses .= ' pb-image-section--ratio-'.$ratio;
    }
@endphp
<section class="pb-section pb-section--image-section pb-image-section pb-align--{{ $data['align'] ?? 'center' }} {{ $sectionClasses }}">
    @if ($src !== '')
        <figure class="pb-image-section__figure">
            <img class="pb-image-section__img" src="{{ $src }}" alt="{{ $alt }}">
            @if ($caption !== '')
                <figcaption class="pb-image-section__caption">{{ $caption }}</figcaption>
            @endif
        </figure>
    @elseif ($pbEditor ?? false)
        <div class="pb-placeholder">{{ __('Choose an image') }}</div>
    @endif
    {!! $children !!}
</section>
