{{--
    Container block (Phase 25.3; runtime mapping 25.3.1; flex-gutter model 25.3.2).

    Lays its columns out horizontally by default (flex, row, wrap). Spacing between
    columns is a GUTTER, not a flex `gap`: a flex `gap` is added ON TOP of the
    percentage bases, so two 50% columns + a gap overflow 100% and wrap onto two
    rows (25.3.2 §5). Instead the container exposes `--pb-col-gap` and each column
    pads itself by half of it with `box-sizing:border-box`, so exact percentages
    always sum to <= 100% and never wrap. Self-contained inline styles (no external
    CSS). Editor mode adds a boundary class and an empty-state placeholder — never
    present on the public frontend.
--}}
@php
    $layout = $data['layout'] ?? 'flex';
    $direction = ($data['direction'] ?? 'row') === 'column' ? 'column' : 'row';
    $width = $data['width'] ?? 'normal';
    $gap = $data['gap'] ?? 'md';
    $padding = $data['padding'] ?? 'none';
    $align = $data['align'] ?? 'stretch';
    $justify = $data['justify'] ?? 'start';
    $bg = trim((string) ($data['background'] ?? ''));

    $space = ['none' => '0px', 'sm' => '0.5rem', 'md' => '1rem', 'lg' => '2rem'];
    $flexMap = ['start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end', 'stretch' => 'stretch', 'between' => 'space-between', 'around' => 'space-around'];
    $gutter = $space[$gap] ?? '1rem';

    $styles = match ($layout) {
        'grid' => 'display:grid;grid-auto-flow:column;grid-auto-columns:1fr;gap:'.$gutter.';',
        'block' => 'display:block;',
        // Flex row/column: NO flex gap — the gutter is column padding (see above).
        // `gap:0` is set EXPLICITLY (not just omitted) so a stale/legacy public
        // stylesheet that still sets a flex `gap` on `.pb-container` can never push
        // exact percentages past 100% and wrap them (parity hotfix).
        default => 'display:flex;flex-wrap:wrap;flex-direction:'.$direction.';gap:0;--pb-col-gap:'.$gutter.';',
    };
    if (($space[$padding] ?? '0px') !== '0px') {
        $styles .= 'padding:'.$space[$padding].';';
    }
    if ($layout === 'flex') {
        $styles .= 'align-items:'.($flexMap[$align] ?? 'stretch').';';
        if ($justify !== '' && $justify !== 'start') {
            $styles .= 'justify-content:'.($flexMap[$justify] ?? 'flex-start').';';
        }
    }
    if ($bg !== '') {
        $styles .= 'background:'.e($bg).';';
    }

    // Emit the layout/direction/gap as CLASSES too: the theme's output sanitizer
    // strips the inline flex styles + `--pb-col-gap` below, so the public bundle
    // reconstructs the flex row and the gutter variable from these classes.
    $classes = 'pb-block pb-block--container pb-container pb-layout-container'
        .' pb-layout-container--'.$direction
        .' pb-layout-container--gap-'.$gap
        .' pb-container--'.$width.' pb-container--'.$layout;

    // Dynamic Style Runtime (Phase 26.2.1): the sanitizer strips the arbitrary /
    // enum VISUAL styles above (padding, alignment, background) from the public
    // body — only the structural flex/grid/gutter is reconstructed from classes.
    // Register those visual declarations as a `.pb-ds-*` head class that survives.
    // (display:flex itself comes from the structural class, so alignment applies.)
    $dynamic = ['background' => $bg];
    if (($space[$padding] ?? '0px') !== '0px') {
        $dynamic['padding'] = $space[$padding];
    }
    if ($layout === 'flex') {
        $dynamic['align-items'] = $flexMap[$align] ?? 'stretch';
        if ($justify !== '' && $justify !== 'start') {
            $dynamic['justify-content'] = $flexMap[$justify] ?? 'flex-start';
        }
    }
    $dsClass = app(\Plugins\PageBuilder\Support\DynamicStyle\DynamicStyleRegistry::class)->register($dynamic);
    if ($dsClass !== null) {
        $classes .= ' '.$dsClass;
    }

    if ($pbEditor ?? false) {
        $classes .= ' pb-layout-container--editor';
    }

    $nested = \Plugins\PageBuilder\Facades\PageBuilder::renderer()->renderChildren($data['blocks'] ?? []);
@endphp
<div class="{{ $classes }}" style="{{ $styles }}">
    @if (trim($nested) === '' && ($pbEditor ?? false))
        <div class="pb-layout-container__empty">{{ __('Add a column to this container') }}</div>
    @else
        {!! $nested !!}
    @endif
</div>
