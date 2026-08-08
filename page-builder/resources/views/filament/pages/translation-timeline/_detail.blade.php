{{-- Timeline detail (LT-9C). Metadata + snapshot references + chain info. Never raw snapshot content. --}}
<div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    @if ($selected === null)
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Select a synchronization to inspect it.') }}</p>
    @else
        <h3 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">{{ __('Synchronization #:id', ['id' => $selected['id']]) }}</h3>

        <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-500 dark:text-gray-400">{{ __('Actor') }}</dt><dd class="text-gray-900 dark:text-gray-100">{{ $selected['actor'] ?? __('system') }}</dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">{{ __('Executed at') }}</dt><dd class="text-gray-900 dark:text-gray-100">{{ $selected['executed_at'] }}</dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">{{ __('Applied / skipped') }}</dt><dd class="text-gray-900 dark:text-gray-100">{{ $selected['applied_count'] }} / {{ $selected['skipped_count'] }}</dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">{{ __('Plan fingerprint') }}</dt><dd class="truncate font-mono text-xs text-gray-700 dark:text-gray-200">{{ $selected['plan_fingerprint'] }}</dd></div>
        </dl>

        {{-- Snapshot REFERENCES only — never blob content --}}
        <div class="mt-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Snapshot references') }}</div>
            <dl class="mt-1 grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">
                <div><dt class="text-gray-500 dark:text-gray-400">{{ __('Baseline before') }}</dt><dd class="truncate font-mono text-gray-700 dark:text-gray-200">{{ $selected['baseline_before'] }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">{{ __('Baseline after') }}</dt><dd class="truncate font-mono text-gray-700 dark:text-gray-200">{{ $selected['baseline_after'] }}</dd></div>
            </dl>
        </div>

        <div class="mt-4 rounded-lg bg-gray-50 p-3 text-xs text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
            {{ __('Replay and explainability are observational. Use the tabs above to request a report; the runtime performs the reconstruction.') }}
        </div>
    @endif
</div>
