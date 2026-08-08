{{-- Explainability report (LT-9C). Metadata-only; re-derived by the runtime, degrades gracefully. --}}
<div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    @if ($selected === null)
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Select a synchronization to explain it.') }}</p>
    @elseif (! $canExplain)
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('You do not have permission to view explanations.') }}</p>
    @else
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Explain #:id', ['id' => $selected['id']]) }}</h3>
            <button type="button" wire:click="requestExplain({{ $selected['id'] }})"
                class="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white">{{ __('Explain') }}</button>
        </div>

        @if ($explain === null)
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Request an explanation to see why each change was applied or kept.') }}</p>
        @else
            <div class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                {{ $explain['mode'] === 'full' ? __('Full explanation (classifications re-derived).') : __('Metadata-only (source snapshots not retained).') }}
            </div>
            <table class="w-full text-left text-xs">
                <thead class="text-gray-400">
                    <tr>
                        <th class="py-1">{{ __('Node') }}</th>
                        <th class="py-1">{{ __('Change') }}</th>
                        <th class="py-1">{{ __('Classification') }}</th>
                        <th class="py-1">{{ __('Decision') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($explain['nodes'] as $node)
                        <tr>
                            <td class="py-1 font-mono text-gray-800 dark:text-gray-200">{{ $node['origin'] }}</td>
                            <td class="py-1 text-gray-600 dark:text-gray-300">{{ str_replace('_', ' ', $node['source_change']) }}</td>
                            <td class="py-1 text-gray-600 dark:text-gray-300">{{ $node['classification'] ? str_replace('_', ' ', $node['classification']) : '—' }}</td>
                            <td class="py-1 text-gray-600 dark:text-gray-300">{{ str_replace('_', ' ', $node['decision']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif
</div>
