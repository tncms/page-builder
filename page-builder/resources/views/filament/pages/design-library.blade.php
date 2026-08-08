@php
    // Phase 26.47A — the Design Library nav item points directly at the repository-backed
    // surface (see DesignLibraryPage::getNavigationUrl). This page body only renders when
    // the slug is reached directly; it forwards to that surface so there is a single
    // Design Library destination and no competing "Template Library" screen.
    $designLibraryUrl = \Plugins\PageBuilder\Filament\Pages\DesignLibraryPage::designLibraryUrl();
@endphp

<x-filament-panels::page>
    <div class="pb-muted" style="opacity:.65;font-size:.9rem;">
        <p>{{ tn_trans('Opening the Design Library…') }}</p>
        <p>
            <a href="{{ $designLibraryUrl }}">{{ tn_trans('Open the Design Library') }}</a>
        </p>
    </div>

    <script>window.location.replace(@js($designLibraryUrl));</script>
</x-filament-panels::page>
