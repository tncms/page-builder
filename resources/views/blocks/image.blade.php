{{--
    Image block (Phase 4). Renders nothing without a source (no broken <img>).
    The src is scheme-sanitised; alt/caption are escaped; caption is optional.
--}}
@php
    $src = \Plugins\PageBuilder\Support\Html::safeUrl($data['src'] ?? null, '');
    $alt = \Plugins\PageBuilder\Support\Localize::text($data['alt'] ?? '');
    $caption = \Plugins\PageBuilder\Support\Localize::text($data['caption'] ?? '');
    $ratio = $data['ratio'] ?? null;
    $mods = 'pb-image--'.($data['style'] ?? 'plain');
    if ($ratio) {
        $mods .= ' pb-image--ratio-'.$ratio;
    }
@endphp
@if ($src !== '')
    <figure class="pb-block pb-block--image pb-image {{ $mods }}">
        <img class="pb-image__img" src="{{ $src }}" alt="{{ $alt }}">
        @if ($caption !== '')
            <figcaption class="pb-image__caption">{{ $caption }}</figcaption>
        @endif
    </figure>
@elseif ($pbEditor ?? false)
    <div class="pb-block pb-block--image pb-placeholder">{{ __('Choose an image') }}</div>
@endif
