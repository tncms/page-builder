<script setup lang="ts">
// PB-CORE-AUTHORITY-REMEDIATION-R1 — confirmation for a render-source switch.
//
// States the current and target source, what renders publicly and that the other
// source is kept. Clean editor: Cancel / Switch. Unsaved changes: Cancel / Discard
// changes and switch / Save draft and switch. Accessible like the locale dialog:
// role dialog + aria-modal, Escape cancels, focus trapped, focus starts on Cancel.
import { onBeforeUnmount, onMounted, ref } from 'vue'
import type { EditorMode, ModeSwitchChoice } from '../modeSwitch'

const props = defineProps<{
  current: EditorMode
  target: EditorMode
  dirty: boolean
  busy: boolean
  error: string | null
  t: (key: string, fallback: string) => string
}>()

const emit = defineEmits<{
  (e: 'cancel'): void
  (e: 'choose', choice: ModeSwitchChoice): void
}>()

const dialogEl = ref<HTMLElement | null>(null)
const cancelButton = ref<HTMLButtonElement | null>(null)

function label(mode: EditorMode): string {
  return mode === 'builder'
    ? props.t('modeBuilder', 'Page Builder content')
    : props.t('modeClassic', 'Classic content')
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.preventDefault()
    if (!props.busy) emit('cancel')
    return
  }

  if (event.key !== 'Tab' || !dialogEl.value) {
    return
  }

  const focusable = Array.from(dialogEl.value.querySelectorAll<HTMLElement>('button:not([disabled])'))
  if (focusable.length === 0) {
    return
  }

  const first = focusable[0]
  const last = focusable[focusable.length - 1]
  const active = document.activeElement

  if (event.shiftKey && active === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && active === last) {
    event.preventDefault()
    first.focus()
  }
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown, true)
  cancelButton.value?.focus()
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown, true)
})
</script>

<template>
  <div class="pb-mode-dialog__backdrop" @click.self="!busy && emit('cancel')">
    <div
      ref="dialogEl"
      class="pb-mode-dialog"
      role="dialog"
      aria-modal="true"
      aria-labelledby="pb-mode-dialog-title"
      aria-describedby="pb-mode-dialog-body"
    >
      <h2 id="pb-mode-dialog-title" class="pb-mode-dialog__title">
        {{ t('modeSwitchHeading', 'Change what renders publicly?') }}
      </h2>

      <div id="pb-mode-dialog-body" class="pb-mode-dialog__body">
        <dl class="pb-mode-dialog__states">
          <div>
            <dt>{{ t('modeSwitchCurrent', 'Now rendering publicly') }}</dt>
            <dd data-test="mode-current">{{ label(current) }}</dd>
          </div>
          <div>
            <dt>{{ t('modeSwitchTarget', 'After switching') }}</dt>
            <dd data-test="mode-target">{{ label(target) }}</dd>
          </div>
        </dl>
        <p>
          {{
            target === 'builder'
              ? t('modeSwitchToBuilderNote', 'The published Page Builder layout renders publicly; while no layout is published, visitors keep seeing the classic content. The classic content is kept unchanged.')
              : t('modeSwitchToClassicNote', 'The classic content renders publicly. The Page Builder layout (draft and published) is kept unchanged.')
          }}
        </p>
        <p>{{ t('modeSwitchReversible', 'Nothing is published or deleted, and you can switch back at any time.') }}</p>
        <p v-if="dirty" class="pb-mode-dialog__dirty">
          {{ t('modeSwitchDirty', 'You have unsaved changes in this layout. Save them first, or discard them.') }}
        </p>
      </div>

      <p v-if="error" class="pb-mode-dialog__error" role="alert">{{ error }}</p>

      <div class="pb-mode-dialog__actions">
        <button
          ref="cancelButton"
          type="button"
          class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--ghost"
          :disabled="busy"
          @click="emit('cancel')"
        >
          {{ t('modeSwitchCancel', 'Cancel') }}
        </button>
        <template v-if="dirty">
          <button
            type="button"
            class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--danger"
            :disabled="busy"
            @click="emit('choose', 'discard')"
          >
            {{ t('modeSwitchDiscard', 'Discard changes and switch') }}
          </button>
          <button
            type="button"
            class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--primary"
            :disabled="busy"
            @click="emit('choose', 'save')"
          >
            {{ busy ? t('modeSwitchBusy', 'Switching…') : t('modeSwitchSave', 'Save draft and switch') }}
          </button>
        </template>
        <button
          v-else
          type="button"
          class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--primary"
          :disabled="busy"
          @click="emit('choose', 'switch')"
        >
          {{
            busy
              ? t('modeSwitchBusy', 'Switching…')
              : target === 'builder'
                ? t('modeSwitchConfirmBuilder', 'Switch to Page Builder content')
                : t('modeSwitchConfirmClassic', 'Switch to Classic content')
          }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pb-mode-dialog__backdrop {
  position: fixed;
  inset: 0;
  /* A modal: it must outrank the in-canvas component toolbar (live-toolbar layer). */
  z-index: var(--pb-ui-z-modal);
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 23, 42, 0.55);
}

.pb-mode-dialog {
  width: min(32rem, calc(100vw - 2rem));
  max-height: calc(100vh - 2rem);
  overflow-y: auto;
  padding: 1.5rem;
  border-radius: 0.75rem;
  background: #fff;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.35);
  color: #0f172a;
}

.pb-mode-dialog__title {
  margin: 0 0 0.75rem;
  font-size: 1.125rem;
  font-weight: 600;
}

.pb-mode-dialog__body {
  font-size: 0.925rem;
  line-height: 1.5;
  color: #334155;
}

.pb-mode-dialog__body p {
  margin: 0 0 0.75rem;
}

.pb-mode-dialog__states {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
  gap: 0.5rem;
  margin: 0 0 0.75rem;
}

.pb-mode-dialog__states dt {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
}

.pb-mode-dialog__states dd {
  margin: 0;
  font-weight: 600;
  color: #0f172a;
}

.pb-mode-dialog__dirty {
  color: #92400e;
}

.pb-mode-dialog__error {
  margin: 0 0 1rem;
  font-size: 0.85rem;
  color: #b91c1c;
}

.pb-mode-dialog__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>
