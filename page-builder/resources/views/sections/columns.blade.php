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
    $space = ['none' => '0px', 'sm' => '0.5rem', 'md' => '1rem', 'lg' => '2rem'];
    $gutter = $space[$gap] ?? '1rem';
    // `gap:0` is set EXPLICITLY (not just omitted): the public stylesheet has
    // historically set a flex `gap` on `.pb-columns__inner` (legacy grid model),
    // which the editor CSS does not. Without this inline reset that external gap
    // is added ON TOP of the columns' exact percentages and wraps them onto two
    // rows publicly while the editor stays correct. Inline `gap:0` neutralises any
    // such rule, so the legacy Columns section stays self-contained and matches the
    // Container's flex-gutter model (gutter = column padding via `--pb-col-gap`).
    $innerStyle = 'display:flex;flex-wrap:wrap;flex-direction:row;align-items:stretch;gap:0;--pb-col-gap:'.$gutter.';';
@endphp
<section class="pb-section pb-section--columns pb-columns pb-columns--gap-{{ $gap }} pb-columns--cols-{{ $data['columns'] ?? 'auto' }}">
    <div class="pb-columns__inner" style="{{ $innerStyle }}">
        @if (trim($children) === '' && ($pbEditor ?? false))
            <div class="pb-placeholder">{{ __('Add columns to this row') }}</div>
        @else
            {!! $children !!}
        @endif
    </div>
</section>
