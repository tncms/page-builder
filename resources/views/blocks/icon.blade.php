{{--
    Icon block (Phase 4; bounded public registry PB-DEMO-DESIGN-R1-A-R2;
    sanitizer-proof CSS delivery PB-DEMO-DESIGN-R1-A-R2-H2).

    Emits ONLY a whitelisted, bounded `.pb-icon--{name}` span — no inline <svg>.
    The public content sanitizer (`cms_html()`) strips inline <svg> and all non-class
    attributes, so the icon geometry is delivered by the stylesheet (a `::before`
    CSS mask keyed by the surviving class), from the SAME IconLibrary authority used
    in the editor and preview. An unknown/empty name renders nothing (fail-closed) —
    no arbitrary SVG/markup from a payload can reach the page. A decorative icon is
    an empty span (ignored by assistive tech); a labelled icon carries a
    sanitizer-surviving visually-hidden label.
--}}
@php
    $name = strtolower(trim((string) ($data['name'] ?? '')));
    $label = \Plugins\PageBuilder\Support\Localize::text($data['label'] ?? '');
    $paths = \Plugins\PageBuilder\Support\IconLibrary::paths($name);
@endphp
@if ($paths !== null)
    <span class="pb-block pb-block--icon pb-icon pb-icon--{{ $name }}">@if ($label !== '')<span class="pb-sr-only">{{ $label }}</span>@endif</span>
@endif
