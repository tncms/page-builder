{{--
    Columns section (Phase 4; flex row added 25.3.2). A horizontal row of `column`
    blocks; each column renders its own nested blocks. $children is trusted rendered
    block HTML.

    Legacy columns previously had no layout of their own, so their column markers
    (editor) / column blocks (public) stacked vertically. The inner row is now a
    flex row using the same gutter model as the Container (gutter via `--pb-col-gap`
    + column padding, NOT a flex `gap`, so exact percentages never overflow). Column
    widths still come from each column block / its editor marker, so this only adds
    the missing flex context — it does not add any per-column `flex:0 0` here.
--}}
@php
    $gap = $data['gap'] ?? 'md';
    // Geometry authority (PB-FREE-LIBRARY-DESIGN-1-C): the flex row, its `gap:0`
    // gutter model (gutter = column padding via `--pb-col-gap`) and the tablet
    // stacking are owned entirely by CSS classes (`.pb-columns__inner` +
    // `.pb-columns--gap-*`), so ONE responsive authority governs every surface —
    // no inline `display:flex`/`flex-direction` that a media query cannot override.
    $accent = \Plugins\PageBuilder\Support\AccentPreset::classes($data['accent'] ?? null);
    $variant = in_array($data['variant'] ?? '', ['cards', 'featured', 'timeline'], true) ? ' pb-columns--'.$data['variant'] : '';
    $tone = \Plugins\PageBuilder\Support\TonePreset::classes($data['tone'] ?? null);
@endphp
<section class="pb-section pb-section--columns pb-columns pb-columns--gap-{{ $gap }} pb-columns--cols-{{ $data['columns'] ?? 'auto' }}{{ $accent !== '' ? ' '.$accent : '' }}{{ $variant }}{{ $tone !== '' ? ' '.$tone : '' }}">
    <div class="pb-columns__inner">
        @if (trim($children) === '' && ($pbEditor ?? false))
            <div class="pb-placeholder">{{ __('Add columns to this row') }}</div>
        @else
            {!! $children !!}
        @endif
    </div>
</section>
