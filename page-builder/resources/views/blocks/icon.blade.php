{{--
    Icon block (Phase 4). Renders a scoped span whose modifier is the (escaped)
    icon name; an optional accessible label is escaped. No name renders nothing.
--}}
@php
    $name = (string) ($data['name'] ?? '');
    $label = \Plugins\PageBuilder\Support\Localize::text($data['label'] ?? '');
@endphp
@if ($name !== '')
    <span class="pb-block pb-block--icon pb-icon pb-icon--{{ $name }}"
        @if ($label !== '') role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif></span>
@endif
