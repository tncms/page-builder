{{--
    Reference "text" block (Phase 3; PB-TEXT-AUTHORING-R1).

    $data is the block's normalized data map. A legacy plain `text` value is echoed
    escaped ({{ }}), never raw, exactly as before. Rich `content` written by the
    visual editor is output only through AuthoredHtml (Core cms_html() first, then
    Page Builder's narrower Text vocabulary). TextBlockContent decides, per locale,
    which of the two renders.
--}}
@php
    $resolved = \Plugins\PageBuilder\Support\TextBlockContent::resolve($data);
    $variantClass = in_array($data['variant'] ?? 'default', ['eyebrow', 'lead'], true) ? ' pb-text--'.$data['variant'] : '';
@endphp
@if ($resolved['rich'])
<div class="pb-block pb-block--text pb-text--rich{{ $variantClass }}">{!! \Plugins\PageBuilder\Support\AuthoredHtml::render($resolved['value'], \Plugins\PageBuilder\Support\AuthoredHtml::TEXT) !!}</div>
@else
<div class="pb-block pb-block--text{{ $variantClass }}">{{ $resolved['value'] }}</div>
@endif
