{{--
    Heading block (Phase 4). Renders an <h1>–<h6> chosen from a fixed whitelist
    (never an arbitrary tag), with escaped text. Empty text renders nothing.

    The semantic level is resolved through the render-scoped HeadingRankResolver
    (PB-FREE-LIBRARY-DESIGN-1-RC-B) so the document keeps exactly one <h1>,
    coordinated with the host page-title visibility. The stored `data.level` is
    the author's *intent* and is never mutated; only the emitted tag adapts, and
    the whitelist normalization still guarantees a fixed, safe tag.
--}}
@php
    $text = \Plugins\PageBuilder\Support\Localize::text($data['text'] ?? '');
@endphp
@if ($text !== '')
    @php
        $level = app(\Plugins\PageBuilder\Support\HeadingRankResolver::class)->resolve($data['level'] ?? null);
    @endphp
    <{{ $level }} class="pb-block pb-block--heading{{ in_array($data['size'] ?? 'default', ['sm', 'md', 'lg', 'xl'], true) ? ' pb-heading--'.$data['size'] : '' }}">{{ $text }}</{{ $level }}>
@endif
