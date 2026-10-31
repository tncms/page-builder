{{--
    PB-CORE-AUTHORITY-REMEDIATION-R1 — the blank-layout decision.

    Shown instead of the editor when a translation's source language has no Page
    Builder content to copy. Showing it writes nothing; only "Create blank
    translation" writes (an explicit, re-authorized Livewire action).

    @var array{source_layout: string, locale: string, return_url: ?string, cancel_url: ?string} $decision
--}}
<section
    class="pb-blank-decision"
    role="region"
    aria-labelledby="pb-blank-decision-title"
    data-test="blank-layout-decision"
    data-source-layout="{{ $decision['source_layout'] }}"
>
    <x-filament::section>
        <x-slot name="heading">
            <span id="pb-blank-decision-title">{{ __('No Page Builder content to copy') }}</span>
        </x-slot>

        <p class="pb-blank-decision__message">
            {{ __('The source language has no Page Builder content to copy. Would you like to create a blank translated Page Builder layout and start designing?') }}
        </p>
        <p class="pb-blank-decision__note">
            {{ __('Nothing is published. The source language content stays unchanged.') }}
        </p>

        <div class="pb-blank-decision__actions" style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem;">
            <x-filament::button
                wire:click="createBlankTranslationLayout"
                wire:loading.attr="disabled"
                data-test="blank-decision-create"
            >
                {{ __('Create blank translation') }}
            </x-filament::button>

            @if ($decision['return_url'])
                <x-filament::button tag="a" color="gray" :href="$decision['return_url']" data-test="blank-decision-return">
                    {{ __('Return to content editor') }}
                </x-filament::button>
            @endif

            {{-- Cancel always writes nothing: back to the source-language editor, or to
                 wherever the user came from when that URL cannot be generated. --}}
            @if ($decision['cancel_url'])
                <x-filament::button tag="a" color="gray" outlined :href="$decision['cancel_url']" data-test="blank-decision-cancel">
                    {{ __('Cancel') }}
                </x-filament::button>
            @else
                <x-filament::button color="gray" outlined x-on:click="history.back()" data-test="blank-decision-cancel">
                    {{ __('Cancel') }}
                </x-filament::button>
            @endif
        </div>
    </x-filament::section>
</section>
