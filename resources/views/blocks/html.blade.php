{{--
    Custom HTML block (PB-TEXT-AUTHORING-R1). The stored source is never echoed
    raw: AuthoredHtml runs Core's cms_html() and then Page Builder's narrowing
    (no <h1>, same-origin images only). Empty or refused source renders nothing.
--}}
@php
    $html = \Plugins\PageBuilder\Support\AuthoredHtml::render(\Plugins\PageBuilder\Support\Localize::text($data['html'] ?? ''), \Plugins\PageBuilder\Support\AuthoredHtml::CUSTOM);
@endphp
@if ($html !== '')
<div class="pb-block pb-block--html">{!! $html !!}</div>
@elseif ($pbEditor ?? false)
<div class="pb-block pb-block--html pb-html--empty">{{ __('Custom HTML — add HTML source in the Inspector.') }}</div>
@endif
