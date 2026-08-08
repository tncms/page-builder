{{--
    Availability badge for a Design Library card (Phase 26.45).

    Renders the FREE/PRO availability state as a small pill. It is purely
    presentational — the verdict is decided by ResourceAvailabilityResolver and
    carried on the card. Locked resources stay listed and previewable; the badge
    just signals that import/insert needs an entitlement.

    @var \Plugins\PageBuilder\Library\LibraryCard $card
--}}
@php
    $tierLabel = $card->entitlement !== 'free' ? ucfirst($card->entitlement) : 'PRO';
@endphp
@if ($card->locked)
    <span class="dl-badge dl-badge--pro" data-availability="locked" title="Requires {{ $card->requiredCapability }}">🔒 {{ $tierLabel }} required</span>
@elseif ($card->entitlement !== 'free')
    <span class="dl-badge dl-badge--pro" data-availability="entitled">{{ ucfirst($card->entitlement) }}</span>
@endif
