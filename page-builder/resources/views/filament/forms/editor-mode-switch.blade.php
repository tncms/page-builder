{{--
    Editor-mode switch (Phase 26.1.2 — Admin Editor UX Refresh).

    A segmented control (Classic Editor | UX Page Builder) rendered at the top of
    the Page / Post edit form. The selected mode is a client-side UX preference
    remembered in localStorage — it never writes to the record. Classic mode is
    just a hint (the real title/content fields stay below, unchanged); Builder
    mode surfaces the clean launch panel + human-readable status.

    Data comes from EditorLauncherPanel::viewData().

    @var string      $ownerType
    @var string      $storageKey
    @var string      $savedMode   'classic' | 'builder'
    @var array       $urls        {live, structure, preview}
    @var array       $status      {hasDraft, hasPublished, lastEditedHuman}
--}}
@php
    $liveUrl = $urls['live'] ?? null;
    $structureUrl = $urls['structure'] ?? null;
    $previewUrl = $urls['preview'] ?? null;
@endphp

<div
    class="tn-pb-switch"
    x-data="{
        mode: @js($savedMode),
        storageKey: @js($storageKey),
        order: ['classic', 'builder'],
        init() {
            try {
                const saved = window.localStorage.getItem(this.storageKey);
                if (this.order.includes(saved)) { this.mode = saved; }
            } catch (e) {}
        },
        select(next) {
            if (! this.order.includes(next)) { return; }
            this.mode = next;
            try { window.localStorage.setItem(this.storageKey, next); } catch (e) {}
        },
        onKeydown(event) {
            const keys = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];
            if (! keys.includes(event.key)) { return; }
            event.preventDefault();
            let index = this.order.indexOf(this.mode);
            if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                index = (index + this.order.length - 1) % this.order.length;
            } else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                index = (index + 1) % this.order.length;
            } else if (event.key === 'Home') {
                index = 0;
            } else if (event.key === 'End') {
                index = this.order.length - 1;
            }
            const next = this.order[index];
            this.select(next);
            this.$nextTick(() => { this.$refs['tab_' + next]?.focus(); });
        },
    }"
>
    <div class="tn-pb-switch__tablist" role="tablist" aria-label="{{ __('Editor mode') }}" @keydown="onKeydown($event)">
        <button
            type="button"
            role="tab"
            x-ref="tab_classic"
            id="tn-pb-tab-classic"
            aria-controls="tn-pb-panel-classic"
            :aria-selected="mode === 'classic' ? 'true' : 'false'"
            :tabindex="mode === 'classic' ? 0 : -1"
            :class="{ 'tn-pb-switch__tab--active': mode === 'classic' }"
            class="tn-pb-switch__tab"
            @click="select('classic')"
        >
            <x-filament::icon icon="heroicon-m-pencil-square" class="tn-pb-switch__tab-icon" />
            <span>{{ __('Classic Editor') }}</span>
        </button>

        <button
            type="button"
            role="tab"
            x-ref="tab_builder"
            id="tn-pb-tab-builder"
            aria-controls="tn-pb-panel-builder"
            :aria-selected="mode === 'builder' ? 'true' : 'false'"
            :tabindex="mode === 'builder' ? 0 : -1"
            :class="{ 'tn-pb-switch__tab--active': mode === 'builder' }"
            class="tn-pb-switch__tab"
            @click="select('builder')"
        >
            <x-filament::icon icon="heroicon-m-squares-2x2" class="tn-pb-switch__tab-icon" />
            <span>{{ __('UX Page Builder') }}</span>
        </button>
    </div>

    {{-- Classic mode: the real fields live below, so this is just context. --}}
    <div
        id="tn-pb-panel-classic"
        role="tabpanel"
        aria-labelledby="tn-pb-tab-classic"
        class="tn-pb-switch__panel"
        x-show="mode === 'classic'"
        x-cloak
    >
        <p class="tn-pb-switch__lead">
            {{ __('You are using the Classic Editor. Edit the title, content and settings in the fields below.') }}
        </p>
    </div>

    {{-- Builder mode: clean launch panel + status. --}}
    <div
        id="tn-pb-panel-builder"
        role="tabpanel"
        aria-labelledby="tn-pb-tab-builder"
        class="tn-pb-switch__panel"
        x-show="mode === 'builder'"
        x-cloak
    >
        <div class="tn-pb-switch__builder">
            <div class="tn-pb-switch__builder-copy">
                <h3 class="tn-pb-switch__title">{{ __('UX Page Builder') }}</h3>
                <p class="tn-pb-switch__lead">{{ __('Visual editing enabled.') }}</p>
            </div>

            <div class="tn-pb-switch__actions">
                @if ($liveUrl)
                    <x-filament::button tag="a" href="{{ $liveUrl }}" icon="heroicon-m-rocket-launch" size="lg">
                        {{ __('Launch Frontend Builder') }}
                    </x-filament::button>
                @endif

                {{-- CORE-L10N.1B P3.1: the URL is produced by the Core preview
                     service in the editing locale; the view carries no
                     localization policy and never emits a dead "#" anchor —
                     an unavailable preview renders a disabled control. --}}
                @if ($previewUrl)
                    <x-filament::button tag="a" href="{{ $previewUrl }}" target="_blank" color="gray" icon="heroicon-m-eye">
                        {{ __('Preview') }}
                    </x-filament::button>
                @else
                    <x-filament::button tag="button" type="button" color="gray" icon="heroicon-m-eye" disabled>
                        {{ __('Preview unavailable') }}
                    </x-filament::button>
                @endif

                @if ($structureUrl)
                    <x-filament::button tag="a" href="{{ $structureUrl }}" color="gray" icon="heroicon-m-adjustments-horizontal">
                        {{ __('Builder Settings') }}
                    </x-filament::button>
                @endif
            </div>

            <dl class="tn-pb-switch__status">
                <div class="tn-pb-switch__status-item">
                    <dt>{{ __('Status') }}</dt>
                    <dd>
                        <span class="tn-pb-switch__badge tn-pb-switch__badge--{{ $status['hasPublished'] ? 'success' : 'muted' }}">
                            {{ $status['hasPublished'] ? __('Published') : __('Draft') }}
                        </span>
                        @if ($status['hasDraft'])
                            <span class="tn-pb-switch__badge tn-pb-switch__badge--info">{{ __('Unsaved draft') }}</span>
                        @endif
                    </dd>
                </div>

                @if (! empty($status['lastEditedHuman']))
                    <div class="tn-pb-switch__status-item">
                        <dt>{{ __('Last edited') }}</dt>
                        <dd>{{ $status['lastEditedHuman'] }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>
</div>

@verbatim
<style>
    [x-cloak] { display: none !important; }

    .tn-pb-switch__tablist {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 0.25rem;
        padding: 0.25rem;
        border-radius: 0.75rem;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        max-width: 100%;
    }

    .tn-pb-switch__tab {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.5rem 0.9rem;
        border: 0;
        border-radius: 0.55rem;
        background: transparent;
        color: #4b5563;
        font-size: 0.875rem;
        font-weight: 600;
        line-height: 1.25;
        cursor: pointer;
        transition: background-color .15s ease, color .15s ease, box-shadow .15s ease;
    }

    .tn-pb-switch__tab:hover { color: #111827; }

    .tn-pb-switch__tab:focus-visible {
        outline: 2px solid #6366f1;
        outline-offset: 2px;
    }

    .tn-pb-switch__tab--active {
        background: #ffffff;
        color: #4f46e5;
        box-shadow: 0 1px 2px rgba(0, 0, 0, .08), 0 1px 3px rgba(0, 0, 0, .06);
    }

    .tn-pb-switch__tab-icon { width: 1.05rem; height: 1.05rem; }

    .tn-pb-switch__panel { margin-top: 1.15rem; }

    .tn-pb-switch__lead {
        margin: 0;
        color: #6b7280;
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .tn-pb-switch__builder {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .tn-pb-switch__title {
        margin: 0 0 0.15rem;
        font-size: 1rem;
        font-weight: 700;
        color: #111827;
    }

    .tn-pb-switch__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
    }

    .tn-pb-switch__status {
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
        margin: 0;
        padding-top: 0.25rem;
    }

    .tn-pb-switch__status-item { margin: 0; }

    .tn-pb-switch__status dt {
        margin: 0 0 0.2rem;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #9ca3af;
    }

    .tn-pb-switch__status dd {
        margin: 0;
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        font-size: 0.875rem;
        color: #374151;
    }

    .tn-pb-switch__badge {
        display: inline-flex;
        align-items: center;
        padding: 0.1rem 0.55rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1.4;
    }

    .tn-pb-switch__badge--success { background: #dcfce7; color: #166534; }
    .tn-pb-switch__badge--muted { background: #f3f4f6; color: #4b5563; }
    .tn-pb-switch__badge--info { background: #e0e7ff; color: #3730a3; }

    .dark .tn-pb-switch__tablist {
        background: rgba(255, 255, 255, .05);
        border-color: rgba(255, 255, 255, .1);
    }

    .dark .tn-pb-switch__tab { color: #9ca3af; }
    .dark .tn-pb-switch__tab:hover { color: #f9fafb; }

    .dark .tn-pb-switch__tab--active {
        background: rgba(255, 255, 255, .1);
        color: #c7d2fe;
        box-shadow: none;
    }

    .dark .tn-pb-switch__lead { color: #9ca3af; }
    .dark .tn-pb-switch__title { color: #f9fafb; }
    .dark .tn-pb-switch__status dd { color: #d1d5db; }
    .dark .tn-pb-switch__badge--success { background: rgba(34, 197, 94, .15); color: #86efac; }
    .dark .tn-pb-switch__badge--muted { background: rgba(255, 255, 255, .08); color: #d1d5db; }
    .dark .tn-pb-switch__badge--info { background: rgba(99, 102, 241, .18); color: #c7d2fe; }

    @media (max-width: 640px) {
        .tn-pb-switch__tablist { display: flex; width: 100%; }
        .tn-pb-switch__tab { flex: 1 1 auto; justify-content: center; }
        .tn-pb-switch__actions > * { flex: 1 1 auto; }
    }
</style>
@endverbatim
