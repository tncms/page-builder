{{--
    Synchronization Timeline — read-only presentation shell (LT-9C).

    Renders ONLY the view models produced by the Timeline application/query services. Every read is
    delegated to the frozen Timeline Runtime; this view constructs no timeline, runs no replay, and exposes
    no re-execution / edit / delete. Replay and explainability are observational, permission-gated actions.
--}}
@php($data = $this->viewData())

<x-filament-panels::page>
    @if ($data['blocked'])
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('The synchronization timeline is not available for this target.') }}</p>
        </div>
    @else
        @include('page-builder::filament.pages.translation-timeline._statistics', ['summary' => $data['summary'], 'statistics' => $data['statistics']])

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-5">
            <div class="lg:col-span-2">
                @include('page-builder::filament.pages.translation-timeline._list', ['list' => $data['list']])
            </div>

            <div class="lg:col-span-3">
                <div class="mb-3 flex gap-2">
                    @foreach (['detail' => __('Detail'), 'replay' => __('Replay'), 'explain' => __('Explainability')] as $panel => $label)
                        <button type="button" wire:click="setActivePanel('{{ $panel }}')"
                            @class([
                                'rounded-lg px-3 py-1.5 text-sm font-medium',
                                'bg-primary-600 text-white' => $activePanel === $panel,
                                'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' => $activePanel !== $panel,
                            ])>{{ $label }}</button>
                    @endforeach
                </div>

                @if ($activePanel === 'replay')
                    @include('page-builder::filament.pages.translation-timeline._replay', ['selected' => $data['selected'], 'replay' => $data['replay'], 'canReplay' => $data['can_replay']])
                @elseif ($activePanel === 'explain')
                    @include('page-builder::filament.pages.translation-timeline._explainability', ['selected' => $data['selected'], 'explain' => $data['explain'], 'canExplain' => $data['can_explain']])
                @else
                    @include('page-builder::filament.pages.translation-timeline._detail', ['selected' => $data['selected']])
                @endif
            </div>
        </div>
    @endif
</x-filament-panels::page>
