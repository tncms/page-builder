{{--
    Typed execution-result presentation (LT-8C). Renders the SynchronizationExecutionResult view model
    verbatim — outcome, counts, new baseline fingerprint, history id, detail. It presents the runtime's
    typed result; it never re-interprets or retries execution.
--}}
@php
    $outcomeLabel = fn (string $o): string => match ($o) {
        'executed' => __('Synchronization applied'),
        'no_op' => __('Nothing to apply'),
        'plan_stale' => __('Plan out of date'),
        'concurrent_conflict' => __('Conflicting concurrent change'),
        'acceptance_invalid' => __('Invalid decisions'),
        'correspondence_fault' => __('Could not apply to the target'),
        default => __('Not applied'),
    };
@endphp

<div @class([
    'fi-section rounded-xl bg-white p-6 shadow-sm ring-1 dark:bg-gray-900',
    'ring-success-600/20' => $result['successful'],
    'ring-danger-600/20' => ! $result['successful'],
])>
    <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $outcomeLabel($result['outcome']) }}</h3>

    @if ($result['mutated'])
        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Applied') }}</dt>
                <dd class="font-semibold text-gray-950 dark:text-white">{{ $result['applied_count'] }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Skipped') }}</dt>
                <dd class="font-semibold text-gray-950 dark:text-white">{{ $result['skipped_count'] }}</dd>
            </div>
            <div class="col-span-2">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('New baseline') }}</dt>
                <dd class="truncate font-mono text-xs text-gray-700 dark:text-gray-200">{{ $result['new_baseline_fingerprint'] }}</dd>
            </div>
        </dl>
    @elseif ($result['detail'])
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ str_replace('_', ' ', $result['detail']) }}</p>
    @endif
</div>
