{{--
    Button block (Phase 4). Renders an anchor only when it has a label. The href
    is passed through Html::safeUrl() to strip dangerous schemes (e.g.
    javascript:) and then escaped; the style is a scoped class only.
--}}
@php
    $label = \Plugins\PageBuilder\Support\Localize::text($data['label'] ?? '');
    $style = $data['style'] ?? 'primary';
    $size = $data['size'] ?? 'md';
@endphp
@if ($label !== '')
    <a class="pb-block pb-block--button pb-button pb-button--{{ $style }} pb-button--{{ $size }}"
       href="{{ \Plugins\PageBuilder\Support\Html::safeUrl($data['url'] ?? null) }}">{{ $label }}</a>
@endif
