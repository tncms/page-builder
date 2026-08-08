@php
    /** @var array<string, mixed> $status */
    $status = $this->status();
@endphp

<x-filament-panels::page>
    <style>
        .pb-card { border: 1px solid rgba(0,0,0,.08); border-radius: .75rem; padding: 1.25rem; background: var(--fi-color-white, #fff); }
        .dark .pb-card { border-color: rgba(255,255,255,.1); background: rgba(255,255,255,.02); }
        .pb-grid { display: grid; gap: 1rem; }
        @media (min-width: 640px) { .pb-stats { grid-template-columns: repeat(3, 1fr); } }
        .pb-stat { text-align: center; }
        .pb-stat .n { font-size: 2rem; font-weight: 800; line-height: 1; }
        .pb-stat .l { font-size: .78rem; opacity: .65; margin-top: .35rem; text-transform: uppercase; letter-spacing: .03em; }
        .pb-pill { display:inline-block; border-radius:1rem; padding:.1rem .6rem; font-size:.9rem; font-weight:700; text-transform:uppercase; }
        .pb-pill-on { background: rgba(22,163,74,.15); color:#16a34a; }
        .pb-pill-off { background: rgba(220,38,38,.15); color:#dc2626; }
        .pb-section-title { font-weight: 700; font-size: 1rem; margin: 1.25rem 0 .75rem; }
        .pb-muted { opacity: .65; font-size: .85rem; }
    </style>

    {{-- Enabled state --}}
    <div class="pb-card">
        <span class="pb-pill {{ $status['enabled'] ? 'pb-pill-on' : 'pb-pill-off' }}">
            {{ $status['enabled'] ? tn_trans('Enabled') : tn_trans('Disabled') }}
        </span>
        <span class="pb-muted" style="margin-left:.75rem;">
            {{ tn_trans('Version') }} {{ $status['version'] }}
        </span>
    </div>

    {{-- Registration counts --}}
    <div class="pb-grid pb-stats" style="margin-top:1rem;">
        <div class="pb-card pb-stat">
            <div class="n">{{ $status['sections_count'] }}</div>
            <div class="l">{{ tn_trans('Registered Sections') }}</div>
        </div>
        <div class="pb-card pb-stat">
            <div class="n">{{ $status['blocks_count'] }}</div>
            <div class="l">{{ tn_trans('Registered Blocks') }}</div>
        </div>
        <div class="pb-card pb-stat">
            <div class="n">{{ $status['sections_count'] + $status['blocks_count'] }}</div>
            <div class="l">{{ tn_trans('Total Types') }}</div>
        </div>
    </div>

    <div class="pb-section-title">{{ tn_trans('About') }}</div>
    <p class="pb-muted">
        {{ tn_trans('The Page Builder stores layouts as a structured tree (sections → blocks), never as raw HTML. The visual editor arrives in a later phase; this dashboard reports the current foundation status.') }}
    </p>
</x-filament-panels::page>
