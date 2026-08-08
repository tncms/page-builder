{{--
    Text section (Phase 4). Prose band with width + alignment settings. Renders
    its own `content` (a translatable plain-text field, escaped with line breaks
    preserved) followed by any heading / text / button child blocks. $children is
    trusted rendered block HTML.
--}}
@php($text = \Plugins\PageBuilder\Support\Localize::text($data['content'] ?? ''))
<section class="pb-section pb-section--text-section pb-text-section pb-text-section--{{ $data['width'] ?? 'normal' }} pb-align--{{ $data['align'] ?? 'left' }}">
    <div class="pb-text-section__inner">
        @if ($text !== '')
            <div class="pb-text-section__content">{!! nl2br(e($text)) !!}</div>
        @endif
        {!! $children !!}
    </div>
</section>
