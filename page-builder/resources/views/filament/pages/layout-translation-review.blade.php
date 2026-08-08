{{--
    Layout Translation Review Workspace — presentation shell (LT-8C).

    Renders ONLY the read-only view models produced by ReviewWorkspaceView. Every action button forwards to
    a single page method that maps 1:1 to a Workspace Runtime operation (INV-LT-8-PRES-01/02). This view
    holds no domain state, constructs no acceptance/command, and never calls the coordinator. Preview is
    whatever the runtime's preview binding returned — including its fail-soft unavailable state.
--}}
@php($data = $this->viewData())

<x-filament-panels::page>
    @if ($data['blocked'])
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $data['block_reason'] }}</p>
        </div>
    @elseif ($data['result'] !== null)
        @include('page-builder::filament.pages.translation-review._result', ['result' => $data['result']])
    @elseif ($data['stale'])
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-warning-600/20 dark:bg-gray-900">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('This review is out of date') }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('The source, target, or baseline changed since this review opened. Re-open the review to continue.') }}
            </p>
        </div>
    @else
        @php($workspace = $data['workspace'])

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
            {{-- Review tree --}}
            <div class="lg:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __(':safe safe · :review to review · :conflict conflicts', [
                            'safe' => $workspace['summary']['safe'],
                            'review' => $workspace['summary']['review_required'],
                            'conflict' => $workspace['summary']['conflict'],
                        ]) }}
                    </div>

                    <button
                        type="button"
                        wire:click="goToNextUnreviewed"
                        @disabled($workspace['undecided_review_count'] === 0)
                        class="fi-btn fi-btn-size-sm rounded-lg bg-primary-600 px-2.5 py-1 text-xs font-medium text-white disabled:opacity-50"
                    >
                        {{ __('Next unreviewed (:n)', ['n' => $workspace['undecided_review_count']]) }}
                    </button>
                </div>

                @include('page-builder::filament.pages.translation-review._tree', [
                    'groups' => $workspace['groups'],
                    'selectedOrigin' => $workspace['selected_origin'],
                    'expandedGroups' => $expandedGroups,
                ])
            </div>

            {{-- Conflict panel --}}
            <div class="lg:col-span-3">
                @include('page-builder::filament.pages.translation-review._conflict-panel', [
                    'preview' => $data['preview'],
                    'selectedOrigin' => $workspace['selected_origin'],
                    'groups' => $workspace['groups'],
                ])

                <div class="mt-6 flex items-center justify-end gap-3">
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __(':n item(s) still need review', ['n' => $workspace['undecided_review_count']]) }}
                    </span>
                    <button
                        type="button"
                        wire:click="submitReview"
                        wire:confirm="{{ __('Apply the accepted changes to this translation?') }}"
                        class="fi-btn fi-btn-size-md rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white"
                    >
                        {{ __('Apply synchronization') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
