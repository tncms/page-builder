{{--
    Review tree presentation (LT-8C). Read-only render of the grouped view model. Each node is a button
    that maps 1:1 to the page's selectNode() runtime operation. No domain logic here.
--}}
@php
    $classificationLabel = fn (string $c): string => match ($c) {
        'conflict' => __('Conflicts'),
        'review_required' => __('Needs review'),
        'safe' => __('Safe'),
        default => ucfirst(str_replace('_', ' ', $c)),
    };
    $decisionBadge = fn (string $d): string => match ($d) {
        'accept_source' => __('Accept source'),
        'keep_target' => __('Keep target'),
        'ignore' => __('Ignore'),
        default => $d,
    };
@endphp

<div class="space-y-4">
    @foreach ($groups as $group)
        @php($expanded = $expandedGroups[$group['classification']] ?? true)
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <button
                type="button"
                wire:click="toggleGroup('{{ $group['classification'] }}')"
                class="flex w-full items-center justify-between px-4 py-3 text-left"
            >
                <span class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $classificationLabel($group['classification']) }}
                </span>
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    {{ $group['count'] }}
                </span>
            </button>

            @if ($expanded)
                <ul class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($group['nodes'] as $node)
                        <li>
                            <button
                                type="button"
                                wire:click="selectNode('{{ $node['origin'] }}')"
                                @class([
                                    'flex w-full items-center justify-between gap-2 px-4 py-2.5 text-left text-sm',
                                    'bg-primary-50 dark:bg-primary-500/10' => $node['origin'] === $selectedOrigin,
                                ])
                            >
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-gray-900 dark:text-gray-100">{{ $node['origin'] }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ str_replace('_', ' ', $node['source_change']) }}</span>
                                </span>
                                <span @class([
                                    'shrink-0 rounded px-1.5 py-0.5 text-[11px]',
                                    'bg-success-100 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $node['effective_decision'] === 'accept_source',
                                    'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' => $node['effective_decision'] !== 'accept_source',
                                    'ring-1 ring-warning-500/40' => $node['needs_review'] ?? (! $node['decided'] && $group['classification'] !== 'safe'),
                                ])>
                                    {{ $decisionBadge($node['effective_decision']) }}{{ $node['decided'] ? '' : ' *' }}
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach
</div>
