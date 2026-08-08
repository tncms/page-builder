{{--
    Conflict panel + decision controls (LT-8C). Shows the selected item's classification, the runtime
    preview (fail-soft when unavailable), and the three frozen decision controls. Each control maps 1:1 to
    the page's decide()/resetDecision() runtime operation — no decision logic lives here.
--}}
@php
    $selectedNode = null;
    foreach ($groups as $g) {
        foreach ($g['nodes'] as $n) {
            if ($n['origin'] === $selectedOrigin) {
                $selectedNode = $n + ['classification' => $g['classification']];
                break 2;
            }
        }
    }

    $decisionLabel = fn (string $d): string => match ($d) {
        'accept_source' => __('Accept source'),
        'keep_target' => __('Keep target'),
        'ignore' => __('Ignore'),
        default => $d,
    };
    $reasonLabel = fn (?string $r): ?string => $r === null ? null : match ($r) {
        'translated_content_diverged' => __('Source text changed after this was translated.'),
        'source_removed_target_modified' => __('Source removed a node you modified.'),
        'structural_conflict' => __('Source and target changed this node incompatibly.'),
        'missing_correspondence' => __('Correspondence is missing or ambiguous.'),
        default => $r,
    };
@endphp

<div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    @if ($selectedNode === null)
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Select an item on the left to review it.') }}</p>
    @else
        <div class="mb-4">
            <div class="flex items-center gap-2">
                <span class="font-mono text-sm text-gray-900 dark:text-gray-100">{{ $selectedNode['origin'] }}</span>
                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    {{ str_replace('_', ' ', $selectedNode['source_change']) }}
                </span>
            </div>
            @if ($reasonLabel($selectedNode['conflict_reason'] ?? null))
                <p class="mt-1 text-xs text-warning-700 dark:text-warning-400">{{ $reasonLabel($selectedNode['conflict_reason']) }}</p>
            @endif
        </div>

        {{-- Preview: runtime preview binding only; fail-soft when unavailable --}}
        <div class="mb-5">
            @if ($preview === null)
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No preview selected.') }}</p>
            @elseif (! $preview['available'])
                <div class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                    {{ __('Preview unavailable') }}
                    <span class="text-xs">({{ str_replace('_', ' ', $preview['unavailable_reason']) }})</span>
                </div>
            @else
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    @foreach (['baseline' => __('Baseline'), 'source' => __('Source'), 'target' => __('Target')] as $side => $label)
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/50">
                            <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ $label }}</div>
                            <div class="whitespace-pre-wrap break-words text-xs text-gray-700 dark:text-gray-200">{{ $preview[$side] }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Decision controls: the frozen SynchronizationDecision options --}}
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($selectedNode['options'] as $option)
                <button
                    type="button"
                    wire:click="decide('{{ $selectedNode['origin'] }}', '{{ $option }}')"
                    @class([
                        'fi-btn fi-btn-size-sm rounded-lg px-3 py-1.5 text-sm font-medium',
                        'bg-primary-600 text-white' => $selectedNode['effective_decision'] === $option,
                        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' => $selectedNode['effective_decision'] !== $option,
                    ])
                >
                    {{ $decisionLabel($option) }}
                </button>
            @endforeach

            @if ($selectedNode['decided'])
                <button
                    type="button"
                    wire:click="resetDecision('{{ $selectedNode['origin'] }}')"
                    class="ml-auto text-xs text-gray-500 underline dark:text-gray-400"
                >
                    {{ __('Reset to default') }}
                </button>
            @endif
        </div>
    @endif
</div>
