<script setup lang="ts">
// PB-CORE-AUTHORITY-REMEDIATION-R1 — the explicit blank-layout decision.
//
// Shown when the server answers a translation create with the blank-layout decision
// (the source language has no Page Builder content to copy). Three explicit
// actions: Create blank translation / Return to content editor / Cancel. Only the
// first writes. Accessible like the other editor dialogs: role dialog + aria-modal,
// Escape cancels, focus trapped, focus starts on Cancel.
import { onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps<{
  canReturn: boolean
  busy: boolean
  error: string | null
  t: (key: string, fallback: string) => string
}>()

const emit = defineEmits<{
  (e: 'create'): void
  (e: 'return'): void
  (e: 'cancel'): void
}>()

const dialogEl = ref<HTMLElement | null>(null)
const cancelButton = ref<HTMLButtonElement | null>(null)

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
  <div class="pb-blank-dialog__backdrop" @click.self="!busy && emit('cancel')">
    <div
      ref="dialogEl"
      class="pb-blank-dialog"
      role="dialog"
      aria-modal="true"
      aria-labelledby="pb-blank-dialog-title"
      aria-describedby="pb-blank-dialog-body"
      data-test="blank-translation-dialog"
    >
      <h2 id="pb-blank-dialog-title" class="pb-blank-dialog__title">
        {{ t('blankDecisionHeading', 'No Page Builder content to copy') }}
      </h2>

      <div id="pb-blank-dialog-body" class="pb-blank-dialog__body">
        <p>
          {{ t('blankDecisionMessage', 'The source language has no Page Builder content to copy. Would you like to create a blank translated Page Builder layout and start designing?') }}
        </p>
        <p>{{ t('blankDecisionNote', 'Nothing is published. The source language content stays unchanged.') }}</p>
      </div>

      <p v-if="error" class="pb-blank-dialog__error" role="alert">{{ error }}</p>

      <div class="pb-blank-dialog__actions">
        <button
          ref="cancelButton"
          type="button"
          class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--ghost"
          data-test="blank-decision-cancel"
          :disabled="busy"
          @click="emit('cancel')"
        >
          {{ t('blankDecisionCancel', 'Cancel') }}
        </button>
        <button
          v-if="canReturn"
          type="button"
          class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--ghost"
          data-test="blank-decision-return"
          :disabled="busy"
          @click="emit('return')"
        >
          {{ t('blankDecisionReturn', 'Return to content editor') }}
        </button>
        <button
          type="button"
          class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--primary"
          data-test="blank-decision-create"
          :disabled="busy"
          @click="emit('create')"
        >
          {{ busy ? t('blankDecisionBusy', 'Creating…') : t('blankDecisionCreate', 'Create blank translation') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pb-blank-dialog__backdrop {
  position: fixed;
  inset: 0;
  /* A modal: it must outrank the in-canvas component toolbar (live-toolbar layer). */
  z-index: var(--pb-ui-z-modal);
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 23, 42, 0.55);
}

.pb-blank-dialog {
  width: min(32rem, calc(100vw - 2rem));
  max-height: calc(100vh - 2rem);
  overflow-y: auto;
  padding: 1.5rem;
  border-radius: 0.75rem;
  background: #fff;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.35);
  color: #0f172a;
}

.pb-blank-dialog__title {
  margin: 0 0 0.75rem;
  font-size: 1.125rem;
  font-weight: 600;
}

.pb-blank-dialog__body {
  font-size: 0.925rem;
  line-height: 1.5;
  color: #334155;
}

.pb-blank-dialog__body p {
  margin: 0 0 0.75rem;
}

.pb-blank-dialog__error {
  margin: 0 0 1rem;
  font-size: 0.85rem;
  color: #b91c1c;
}

.pb-blank-dialog__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>
