{{--
    Quote block (Phase 4). A <blockquote> with escaped text and an optional
    author / role cite. Renders nothing without quote text; omits the cite
    footer when neither author nor role is present.
--}}
@php
    $text = \Plugins\PageBuilder\Support\Localize::text($data['text'] ?? '');
    $author = \Plugins\PageBuilder\Support\Localize::text($data['author'] ?? '');
    $role = \Plugins\PageBuilder\Support\Localize::text($data['role'] ?? '');
@endphp
@if ($text !== '')
    <blockquote class="pb-block pb-block--quote pb-quote">
        <p class="pb-quote__text">{!! nl2br(e($text)) !!}</p>
        @if ($author !== '' || $role !== '')
            <footer class="pb-quote__cite">
                @if ($author !== '')<span class="pb-quote__author">{{ $author }}</span>@endif
                @if ($role !== '')<span class="pb-quote__role">{{ $role }}</span>@endif
            </footer>
        @endif
    </blockquote>
@endif
