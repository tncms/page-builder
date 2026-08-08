<script setup lang="ts">
// LT-M1B Increment 2B — the unsaved-change dialog shown before switching locale.
//
// Three explicit, localized choices (no ambiguous destructive default): Stay
// (safe, autofocused), Save and switch (awaits the existing draft-save pipeline),
// and Switch without saving (discards unsaved client state). Accessible: role
// dialog + aria-modal, Escape cancels, focus is trapped, and focus lands on the
// safe "Stay" action on open. Rendered by the parent behind a v-if.
import { onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps<{
  targetLabel: string
  saving: boolean
  error: string | null
}>()

const emit = defineEmits<{
  (e: 'stay'): void
  (e: 'discard'): void
  (e: 'save'): void
}>()

const dialogEl = ref<HTMLElement | null>(null)
const stayButton = ref<HTMLButtonElement | null>(null)

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.preventDefault()
    emit('stay')
    return
  }

  if (event.key !== 'Tab' || !dialogEl.value) {
    return
  }

  // Trap focus within the dialog.
  const focusable = Array.from(
    dialogEl.value.querySelectorAll<HTMLElement>('button:not([disabled])'),
  )
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
  // Safe default focus — never the destructive action.
  stayButton.value?.focus()
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown, true)
})
</script>

<template>
  <div class="pb-locale-dialog__backdrop" @click.self="emit('stay')">
    <div
      ref="dialogEl"
      class="pb-locale-dialog"
      role="dialog"
      aria-modal="true"
      aria-labelledby="pb-locale-dialog-title"
      aria-describedby="pb-locale-dialog-body"
    >
      <h2 id="pb-locale-dialog-title" class="pb-locale-dialog__title">
        {{ 'Unsaved changes' }}
      </h2>
      <p id="pb-locale-dialog-body" class="pb-locale-dialog__body">
        You have unsaved changes in the current language. Switching to
        <strong>{{ targetLabel }}</strong> now will discard the unsaved editor state. Do you want to
        continue?
      </p>

      <p v-if="error" class="pb-locale-dialog__error" role="alert">{{ error }}</p>

      <div class="pb-locale-dialog__actions">
        <button
          ref="stayButton"
          type="button"
          class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--ghost"
          :disabled="saving"
          @click="emit('stay')"
        >
          Stay and continue editing
        </button>
        <button
          type="button"
          class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--danger"
          :disabled="saving"
          @click="emit('discard')"
        >
          Switch without saving
        </button>
        <button
          type="button"
          class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--primary"
          :disabled="saving"
          @click="emit('save')"
        >
          {{ saving ? 'Saving…' : 'Save and switch' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pb-locale-dialog__backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 23, 42, 0.55);
}

.pb-locale-dialog {
  width: min(30rem, calc(100vw - 2rem));
  padding: 1.5rem;
  border-radius: 0.75rem;
  background: #fff;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.35);
  color: #0f172a;
}

.pb-locale-dialog__title {
  margin: 0 0 0.5rem;
  font-size: 1.125rem;
  font-weight: 600;
}

.pb-locale-dialog__body {
  margin: 0 0 1rem;
  font-size: 0.925rem;
  line-height: 1.5;
  color: #334155;
}

.pb-locale-dialog__error {
  margin: 0 0 1rem;
  font-size: 0.85rem;
  color: #b91c1c;
}

.pb-locale-dialog__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>
