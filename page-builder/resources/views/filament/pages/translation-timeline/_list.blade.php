{{-- Timeline list (LT-9C). Filtering delegates to runtime projections; sorting/pagination are view-only. --}}
<div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 p-3 dark:border-white/5">
        <input type="text" wire:model.live.debounce.400ms="filterActor" placeholder="{{ __('Filter by actor') }}"
            class="rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-white/10" />
        <button type="button" wire:click="toggleGapsOnly"
            @class([
                'rounded-lg px-2.5 py-1 text-xs font-medium',
                'bg-warning-500 text-white' => $gapsOnly,
                'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' => ! $gapsOnly,
            ])>{{ __('Gaps only') }}</button>
        <select wire:model.live="sort" class="rounded-lg border-gray-300 text-xs dark:bg-gray-800 dark:border-white/10">
            <option value="executed_at">{{ __('Executed at') }}</option>
            <option value="actor">{{ __('Actor') }}</option>
            <option value="applied_count">{{ __('Applied') }}</option>
        </select>
        <button type="button" wire:click="setDirection('{{ $direction === 'asc' ? 'desc' : 'asc' }}')"
            class="rounded-lg bg-gray-100 px-2 py-1 text-xs dark:bg-gray-800">{{ $direction === 'asc' ? '↑' : '↓' }}</button>
    </div>

    @if ($list === null || $list['items'] === [])
        <p class="p-4 text-sm text-gray-500 dark:text-gray-400">{{ __('No synchronizations recorded.') }}</p>
    @else
        <ul class="divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($list['items'] as $item)
                <li>
                    <button type="button" wire:click="selectEntry({{ $item['id'] }})"
                        @class([
                            'flex w-full items-center justify-between gap-2 px-4 py-2.5 text-left text-sm',
                            'bg-primary-50 dark:bg-primary-500/10' => $item['id'] === $selectedEntryId,
                        ])>
                        <span class="min-w-0">
                            <span class="block font-medium text-gray-900 dark:text-gray-100">#{{ $item['id'] }} · {{ $item['actor'] ?? __('system') }}</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $item['executed_at'] }}</span>
                        </span>
                        <span class="flex shrink-0 items-center gap-1">
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600 dark:bg-gray-800 dark:text-gray-300">+{{ $item['applied_count'] }}/-{{ $item['skipped_count'] }}</span>
                            @if ($item['has_gap'])
                                <span class="rounded bg-warning-100 px-1.5 py-0.5 text-[11px] text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">{{ __('gap') }}</span>
                            @endif
                        </span>
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="flex items-center justify-between p-3 text-xs text-gray-500 dark:text-gray-400">
            <span>{{ __('Page :p of :n (:t total)', ['p' => $list['page'], 'n' => $list['page_count'], 't' => $list['total']]) }}</span>
            <span class="flex gap-2">
                <button type="button" wire:click="goToPage({{ $list['page'] - 1 }})" @disabled($list['page'] <= 1) class="rounded bg-gray-100 px-2 py-1 disabled:opacity-40 dark:bg-gray-800">{{ __('Prev') }}</button>
                <button type="button" wire:click="goToPage({{ $list['page'] + 1 }})" @disabled($list['page'] >= $list['page_count']) class="rounded bg-gray-100 px-2 py-1 disabled:opacity-40 dark:bg-gray-800">{{ __('Next') }}</button>
            </span>
        </div>
    @endif
</div>
