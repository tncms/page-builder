{{--
    Logo block (Phase 4). Renders nothing without a source. The image is wrapped
    in a link only when a URL is present. src/url are scheme-sanitised; alt is
    escaped.
--}}
@php
    $src = \Plugins\PageBuilder\Support\Html::safeUrl($data['src'] ?? null, '');
    $url = (string) ($data['url'] ?? '');
    $alt = \Plugins\PageBuilder\Support\Localize::text($data['alt'] ?? '');
@endphp
@if ($src !== '')
    <div class="pb-block pb-block--logo pb-logo">
        @if ($url !== '')
            <a class="pb-logo__link" href="{{ \Plugins\PageBuilder\Support\Html::safeUrl($url) }}">
                <img class="pb-logo__img" src="{{ $src }}" alt="{{ $alt }}">
            </a>
        @else
            <img class="pb-logo__img" src="{{ $src }}" alt="{{ $alt }}">
        @endif
    </div>
@endif
