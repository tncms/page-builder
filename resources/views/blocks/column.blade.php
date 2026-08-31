{{--
    Column block (Phase 4; % widths + style Phase 25.3; runtime mapping 25.3.1;
    flex-gutter + mode-aware basis 25.3.2).

    A column is a flex item of its Container. In PUBLIC mode this `.pb-layout-column`
    IS the flex item, so it carries the flex-basis. In EDITOR mode it is wrapped in
    an `.pb-editor-render-marker` that carries the basis instead (the marker is the
    Container's real flex child), so here the column just fills that marker at 100%.
    Either way `box-sizing:border-box` + a half-gutter inline padding (from the
    Container's inherited `--pb-col-gap`) keep exact percentages summing to <= 100%,
    so columns never wrap (25.3.2 §5).

    Legacy semantic widths (auto/half/third/quarter) keep their class-only output.
    Percentage widths (any safe %) add the `pb-layout-column` class. Editor mode
    adds a boundary class and, for an empty column, a "+ Add Element" drop zone —
    never present on the public frontend.
--}}
@php
    use Plugins\PageBuilder\Support\ColumnWidth;

    $width = $data['width'] ?? 'auto';
    $editor = (bool) ($pbEditor ?? false);
    $classes = 'pb-block pb-block--column pb-column pb-column--'.$width;

    // Theme-neutral surface preset (PB-FREE-LIBRARY-DESIGN-1-D): a bounded enum
    // maps to an allowlisted, token-based class. Any other value (default `none`,
    // absent, or a hostile string) emits nothing — fail closed, no raw class.
    $surfaceClass = ['surface' => 'pb-column--surface', 'card' => 'pb-column--card'][$data['surface'] ?? 'none'] ?? '';
    if ($surfaceClass !== '') {
        $classes .= ' '.$surfaceClass;
    }

    $styles = '';
    $basis = ColumnWidth::explicitBasis($width);
    if ($basis !== null) {
        $classes .= ' pb-layout-column';
        $styles .= 'box-sizing:border-box;min-width:0;';
        if ($editor) {
            // Inside a marker that already carries the basis — just fill it.
            $styles .= 'width:100%;max-width:100%;';
        } else {
            // Public root: the theme's output sanitizer strips inline styles, so the
            // basis is carried by a stable width CLASS whose `.pb-cw-*` rule is emitted
            // into <head> (see ColumnWidthRegistry). The inline styles are kept for the
            // sanitizer-free Live Builder / previews and as a progressive-enhancement.
            // Geometry authority (PB-FREE-LIBRARY-DESIGN-1-C): the flex-basis and
            // max-width come from the sanitizer-proof `.pb-cw-*` class (head rule),
            // NOT an inline final declaration — so ONE CSS authority sizes the
            // column on every surface and the tablet stacking media query can win.
            $cwClass = app(\Plugins\PageBuilder\Support\ColumnWidthRegistry::class)->classFor($basis);
            if ($cwClass !== null) {
                $classes .= ' '.$cwClass;
            }
        }
        // Gutter (Strategy A): half of the container's --pb-col-gap, inside the
        // basis thanks to border-box, so it never adds to the column's width.
        $styles .= 'padding-inline:calc(var(--pb-col-gap, 0px) / 2);';
    }

    // Alignment (Phase 25.3.1). A column stacks its children vertically, so it
    // becomes a flex column only when an alignment is actually chosen: vertical
    // align → justify-content (main axis), horizontal align → align-items (cross).
    $flexMap = ['start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end', 'stretch' => 'stretch'];
    $valign = $data['valign'] ?? '';
    $halign = $data['halign'] ?? '';
    $needsFlex = ($valign !== '' && $valign !== 'start') || ($halign !== '' && $halign !== 'stretch');
    if ($needsFlex) {
        $styles .= 'display:flex;flex-direction:column;';
        if ($valign !== '' && $valign !== 'start') {
            $styles .= 'justify-content:'.($flexMap[$valign] ?? 'flex-start').';';
        }
        if ($halign !== '' && $halign !== 'stretch') {
            $styles .= 'align-items:'.($flexMap[$halign] ?? 'stretch').';';
        }
    }

    $padding = $data['padding'] ?? '';
    $space = ['sm' => '0.5rem', 'md' => '1rem', 'lg' => '2rem'];
    if (isset($space[$padding])) {
        $styles .= 'padding:'.$space[$padding].';';
    }

    $bg = trim((string) ($data['background'] ?? ''));
    if ($bg !== '') {
        $styles .= 'background:'.e($bg).';';
    }

    // Dynamic Style Runtime (Phase 26.2.1): the sanitizer strips the inline
    // alignment / padding / background above from the public body, so register the
    // same VISUAL declarations as a `.pb-ds-*` head class that survives. Width
    // stays on its own `.pb-cw-*` class (above); the structural gutter/box-model
    // is carried by `.pb-layout-column`. Inline styles are kept for the editor.
    $dynamic = ['background' => $bg];
    if ($needsFlex) {
        $dynamic['display'] = 'flex';
        $dynamic['flex-direction'] = 'column';
        if ($valign !== '' && $valign !== 'start') {
            $dynamic['justify-content'] = $flexMap[$valign] ?? 'flex-start';
        }
        if ($halign !== '' && $halign !== 'stretch') {
            $dynamic['align-items'] = $flexMap[$halign] ?? 'stretch';
        }
    }
    if (isset($space[$padding])) {
        $dynamic['padding'] = $space[$padding];
    }
    $dsClass = app(\Plugins\PageBuilder\Support\DynamicStyle\DynamicStyleRegistry::class)->register($dynamic);
    if ($dsClass !== null) {
        $classes .= ' '.$dsClass;
    }

    if ($editor) {
        $classes .= ' pb-layout-column--editor';
    }

    $nested = \Plugins\PageBuilder\Facades\PageBuilder::renderer()->renderChildren($data['blocks'] ?? []);
@endphp
<div class="{{ $classes }}"@if ($styles !== '') style="{{ $styles }}"@endif>
    @if (trim($nested) === '' && $editor)
        <button type="button" class="pb-layout-column__dropzone" data-pb-add-element>
            <span class="pb-layout-column__dropzone-icon">+</span>
            <span>{{ __('Add Element') }}</span>
        </button>
    @else
        {!! $nested !!}
    @endif
</div>
