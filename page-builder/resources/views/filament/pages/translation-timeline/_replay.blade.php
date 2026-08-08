{{-- Replay report (LT-9C). Observational only — the UI requests replay; the Replay Runtime reconstructs. --}}
<div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    @if ($selected === null)
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Select a synchronization to replay it.') }}</p>
    @elseif (! $canReplay)
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('You do not have permission to replay synchronizations.') }}</p>
    @else
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Replay #:id', ['id' => $selected['id']]) }}</h3>
            <button type="button" wire:click="requestReplay({{ $selected['id'] }})"
                class="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white">{{ __('Run replay') }}</button>
        </div>

        @if ($replay === null)
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Request a replay to reconstruct this synchronization in a read-only sandbox.') }}</p>
        @else
            @php
                $status = $replay['status'];
                $label = match ($status) {
                    'reproduced' => __('Reproduced — the recorded outcome was reproduced exactly.'),
                    'diverged' => __('Diverged — the reconstruction did not match the recorded outcome.'),
                    default => __('Unavailable — the inputs for this synchronization are not retained.'),
                };
            @endphp
            <div @class([
                'rounded-lg p-4 text-sm ring-1',
                'ring-success-600/20 text-success-800 dark:text-success-300' => $status === 'reproduced',
                'ring-danger-600/20 text-danger-800 dark:text-danger-300' => $status === 'diverged',
                'ring-gray-400/20 text-gray-600 dark:text-gray-300' => $status === 'unavailable',
            ])>
                <div class="font-medium">{{ $label }}</div>
                @if ($replay['difference'] !== null)
                    <dl class="mt-2 text-xs">
                        <div><dt class="inline text-gray-500 dark:text-gray-400">{{ __('Reason:') }}</dt> <dd class="inline">{{ str_replace('_', ' ', $replay['difference']['reason']) }}</dd></div>
                        @if ($replay['difference']['differing_origins'] !== [])
                            <div><dt class="inline text-gray-500 dark:text-gray-400">{{ __('Differing nodes:') }}</dt> <dd class="inline font-mono">{{ implode(', ', $replay['difference']['differing_origins']) }}</dd></div>
                        @endif
                    </dl>
                @elseif ($replay['detail'] !== null)
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ str_replace('_', ' ', $replay['detail']) }}</div>
                @endif
            </div>
        @endif
    @endif
</div>
