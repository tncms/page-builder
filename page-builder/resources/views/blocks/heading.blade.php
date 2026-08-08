{{--
    Heading block (Phase 4). Renders an <h1>–<h6> chosen from a fixed whitelist
    (never an arbitrary tag), with escaped text. Empty text renders nothing.
--}}
@php
    $level = in_array($data['level'] ?? null, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)
        ? $data['level']
        : 'h2';
    $text = \Plugins\PageBuilder\Support\Localize::text($data['text'] ?? '');
@endphp
@if ($text !== '')
    <{{ $level }} class="pb-block pb-block--heading">{{ $text }}</{{ $level }}>
@endif
