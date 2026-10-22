{{--
    Call-to-action section (Phase 4 / 15.1). Own editable title / text / button
    (translatable + escaped), followed by any trusted child blocks.
--}}
@php
    $title = \Plugins\PageBuilder\Support\Localize::text($data['title'] ?? '');
    $text = \Plugins\PageBuilder\Support\Localize::text($data['text'] ?? '');
    $buttonLabel = \Plugins\PageBuilder\Support\Localize::text($data['button_label'] ?? '');
    $buttonUrl = $data['button_url'] ?? null;
    $accent = \Plugins\PageBuilder\Support\AccentPreset::classes($data['accent'] ?? null);
    $tone = \Plugins\PageBuilder\Support\TonePreset::classes($data['tone'] ?? null);
@endphp
<section class="pb-section pb-section--cta pb-cta pb-align--{{ $data['align'] ?? 'center' }}{{ $accent !== '' ? ' '.$accent : '' }}{{ $tone !== '' ? ' '.$tone : '' }}">
    <div class="pb-cta__inner">
        @if ($title !== '')
            <h2 class="pb-cta__title">{{ $title }}</h2>
        @endif
        @if ($text !== '')
            <p class="pb-cta__text">{!! nl2br(e($text)) !!}</p>
        @endif
        @if ($buttonLabel !== '')
            <a class="pb-cta__button pb-button pb-button--primary"
               href="{{ \Plugins\PageBuilder\Support\Html::safeUrl($buttonUrl) }}">{{ $buttonLabel }}</a>
        @endif
        {!! $children !!}
    </div>
</section>
