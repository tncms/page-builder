{{--
    Testimonial section (Phase 4 / 15.1). Renders its own pull-quote
    (translatable + escaped) when set, plus any trusted child quote blocks.
--}}
@php
    $quote = \Plugins\PageBuilder\Support\Localize::text($data['quote'] ?? '');
    $author = \Plugins\PageBuilder\Support\Localize::text($data['author'] ?? '');
    $role = \Plugins\PageBuilder\Support\Localize::text($data['role'] ?? '');
    $accent = \Plugins\PageBuilder\Support\AccentPreset::classes($data['accent'] ?? null);
    $tone = \Plugins\PageBuilder\Support\TonePreset::classes($data['tone'] ?? null);
    $feature = (($data['variant'] ?? '') === 'feature') ? ' pb-testimonial--feature' : '';
@endphp
<section class="pb-section pb-section--testimonial pb-testimonial{{ $feature }}{{ $accent !== '' ? ' '.$accent : '' }}{{ $tone !== '' ? ' '.$tone : '' }}">
    <div class="pb-testimonial__inner">
        @if ($quote !== '')
            <blockquote class="pb-testimonial__quote">
                <p class="pb-testimonial__text">{!! nl2br(e($quote)) !!}</p>
                @if ($author !== '' || $role !== '')
                    <footer class="pb-testimonial__cite">
                        @if ($author !== '')<span class="pb-testimonial__author">{{ $author }}</span>@endif
                        @if ($role !== '')<span class="pb-testimonial__role">{{ $role }}</span>@endif
                    </footer>
                @endif
            </blockquote>
        @endif
        {!! $children !!}
    </div>
</section>
