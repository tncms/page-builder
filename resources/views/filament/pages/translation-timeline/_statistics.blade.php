{{-- Statistics + summary panel (LT-9C). Derived, read-only. --}}
<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    @php
        $cards = [
            ['label' => __('Syncs'), 'value' => $statistics['entry_count']],
            ['label' => __('Gaps'), 'value' => $statistics['gap_count']],
            ['label' => __('Reproduced'), 'value' => $statistics['success_count']],
            ['label' => __('Diverged'), 'value' => $statistics['divergence_count']],
            ['label' => __('Unavailable'), 'value' => $statistics['failure_count']],
        ];
    @endphp
    @foreach ($cards as $card)
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $card['label'] }}</div>
            <div class="text-xl font-semibold text-gray-950 dark:text-white">{{ $card['value'] }}</div>
        </div>
    @endforeach
    <div @class([
        'rounded-xl p-4 shadow-sm ring-1',
        'bg-white ring-success-600/20 dark:bg-gray-900' => $summary['continuous'],
        'bg-white ring-warning-600/30 dark:bg-gray-900' => ! $summary['continuous'],
    ])>
        <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Chain') }}</div>
        <div class="text-sm font-semibold text-gray-950 dark:text-white">
            {{ $summary['continuous'] ? __('Continuous') : __('Has gaps') }}
        </div>
    </div>
</div>
