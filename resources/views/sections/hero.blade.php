{{--
    Hero section (Phase 4 / 15.1; media/split preset PB-DEMO-DESIGN-R1-A).

    A full-width intro band with its own editable title / subtitle / button
    (all translatable + escaped), followed by any trusted child blocks. So a
    freshly-added Hero shows content without requiring child blocks.

    ADDITIVE, OPT-IN. A hero with no `eyebrow` and `layout != split` renders the
    exact stacked markup it always has (every new branch is a strict no-op when its
    field is absent). The opt-in `eyebrow` adds a kicker; the opt-in Split layout
    anchors the hero with its own `image_url` media beside the content; the opt-in
    bounded `accent` recolours its controls. None of these touch heading ownership —
    the title still resolves through HeadingRankResolver so the document keeps
    exactly one <h1>.
--}}
@php
    $title = \Plugins\PageBuilder\Support\Localize::text($data['title'] ?? '');
    $subtitle = \Plugins\PageBuilder\Support\Localize::text($data['subtitle'] ?? '');
    $buttonLabel = \Plugins\PageBuilder\Support\Localize::text($data['button_label'] ?? '');
    $buttonUrl = $data['button_url'] ?? null;
    $style = $data['style'] ?? 'plain';
    $width = $data['width'] ?? 'normal';
    $align = $data['align'] ?? 'center';

    // Opt-in extras (all no-ops when their field is absent).
    $eyebrow = \Plugins\PageBuilder\Support\Localize::text($data['eyebrow'] ?? '');
    $accentClass = \Plugins\PageBuilder\Support\AccentPreset::classes($data['accent'] ?? null);
    $imageUrl = \Plugins\PageBuilder\Support\Html::safeUrl($data['image_url'] ?? null, '');
    $imageAlt = \Plugins\PageBuilder\Support\Localize::text($data['image_alt'] ?? '');
    $isSplit = ($data['layout'] ?? 'stacked') === 'split' && $imageUrl !== '';
    $mediaStart = ($data['media_position'] ?? 'end') === 'start';
    $mediaShape = in_array($data['media_shape'] ?? 'rounded', ['plain', 'rounded', 'shadow'], true) ? ($data['media_shape'] ?? 'rounded') : 'rounded';

    $rootClass = 'pb-section pb-section--hero pb-hero pb-hero--'.$style.' pb-align--'.$align;
    if ($isSplit) {
        $rootClass .= ' pb-hero--split';
    }
    if ($accentClass !== '') {
        $rootClass .= ' '.$accentClass;
    }
@endphp
@if ($isSplit)
<section class="{{ $rootClass }}">
    <div class="pb-hero__inner pb-container--{{ $width }}">
        <div class="pb-hero__split{{ $mediaStart ? ' pb-hero__split--media-start' : '' }}">
            <div class="pb-hero__content">
                @if ($eyebrow !== '')
                    <p class="pb-hero__eyebrow">{{ $eyebrow }}</p>
                @endif
                @if ($title !== '')
                    @php
                        $titleTag = app(\Plugins\PageBuilder\Support\HeadingRankResolver::class)->resolve('h1');
                    @endphp
                    <{{ $titleTag }} class="pb-hero__title">{{ $title }}</{{ $titleTag }}>
                @endif
                @if ($subtitle !== '')
                    <p class="pb-hero__subtitle">{!! nl2br(e($subtitle)) !!}</p>
                @endif
                <div class="pb-hero__actions">
                    @if ($buttonLabel !== '')
                        <a class="pb-hero__button pb-button pb-button--primary"
                           href="{{ \Plugins\PageBuilder\Support\Html::safeUrl($buttonUrl) }}">{{ $buttonLabel }}</a>
                    @endif
                    {!! $children !!}
                </div>
            </div>
            <div class="pb-hero__media pb-hero__media--{{ $mediaShape }}">
                <img class="pb-hero__media-img" src="{{ $imageUrl }}" alt="{{ $imageAlt }}">
            </div>
        </div>
    </div>
</section>
@else
<section class="{{ $rootClass }}">
    <div class="pb-hero__inner pb-container--{{ $width }}">
        @if ($title !== '')
            {{--
                Heading rank (PB-FREE-LIBRARY-DESIGN-1-E-H1 / RC-B). The hero title
                intends to be the builder document's primary heading; the shared
                HeadingRankResolver decides its tag so the document keeps exactly one
                <h1> (single <h1> when the theme title is hidden, <h2> when the host
                already owns the <h1>). Only the native tag changes — the
                `pb-hero__title` class, and therefore all styling, is identical.
            --}}
            @php
                $titleTag = app(\Plugins\PageBuilder\Support\HeadingRankResolver::class)->resolve('h1');
            @endphp
            <{{ $titleTag }} class="pb-hero__title">{{ $title }}</{{ $titleTag }}>
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
@endif
