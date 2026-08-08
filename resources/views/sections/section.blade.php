{{--
    Generic section (Phase 25.3) — the neutral band at the top of the composition
    tree (Section → Container → Column → Elements). Renders its pre-rendered child
    blocks ($children), with a simple width/padding/background shell.
--}}
@php
    $width = $data['width'] ?? 'normal';
    $padding = $data['padding'] ?? 'md';
    $bg = trim((string) ($data['background'] ?? ''));

    $space = ['none' => '0', 'sm' => '1rem', 'md' => '2rem', 'lg' => '4rem'];
    $pad = $space[$padding] ?? '2rem';
    $styles = 'padding-top:'.$pad.';padding-bottom:'.$pad.';';
    if ($bg !== '') {
        $styles .= 'background:'.e($bg).';';
    }

    // Dynamic Style Runtime (Phase 26.2.1): the theme output sanitizer strips the
    // inline styles above from the anonymous frontend, so the same declarations are
    // registered as a `.pb-ds-*` head class that survives. Inline styles are kept
    // for the sanitizer-free editor / previews.
    $dsClass = app(\Plugins\PageBuilder\Support\DynamicStyle\DynamicStyleRegistry::class)->register([
        'padding-top' => $pad,
        'padding-bottom' => $pad,
        'background' => $bg,
    ]);
    $classes = 'pb-section pb-section--section pb-section--w-'.$width.($dsClass ? ' '.$dsClass : '');
@endphp
<section class="{{ $classes }}" style="{{ $styles }}">
    <div class="pb-section__inner">
        @if (trim($children ?? '') === '' && ($pbEditor ?? false))
            <div class="pb-placeholder">{{ __('Add a container or blocks here') }}</div>
        @else
            {!! $children ?? '' !!}
        @endif
    </div>
</section>
