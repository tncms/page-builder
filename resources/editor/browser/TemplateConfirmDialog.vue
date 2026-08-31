<script setup lang="ts">
// PB-TEMPLATE-REGISTRY-2-C — stage 2 of the Template replacement flow. Applying a Template
// is destructive to the CURRENT DRAFT, so this modal states the truth: the draft layout is
// replaced, the published page is unchanged until Publish, and unsaved draft work is
// replaced. Cancel closes with ZERO apply request; Confirm delegates ONE apply to the store
// (guarded against double-submit); Escape cancels — it never confirms.
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useResourceBrowserStore } from './resourceBrowserStore'

const store = useResourceBrowserStore()

const box = ref<HTMLElement | null>(null)
let lastFocus: HTMLElement | null = null

const s = (key: string, fallback = ''): string => store.label(key, fallback)

function focusables(): HTMLElement[] {
  if (!box.value) return []
  return Array.from(box.value.querySelectorAll<HTMLElement>('button:not([disabled]), [tabindex]:not([tabindex="-1"])'))
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.preventDefault()
    event.stopPropagation()
    store.cancelTemplate() // Escape can never confirm a destructive replace.
    return
  }
  if (event.key === 'Tab') {
    const f = focusables()
    if (!f.length) return
    const first = f[0]
    const last = f[f.length - 1]
    if (event.shiftKey && document.activeElement === first) {
      last.focus()
      event.preventDefault()
    } else if (!event.shiftKey && document.activeElement === last) {
      first.focus()
      event.preventDefault()
    }
  }
}

onMounted(() => {
  lastFocus = (document.activeElement as HTMLElement) ?? null
  box.value?.focus()
})
onBeforeUnmount(() => {
  if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus()
})
</script>

<template>
  <div
    v-if="store.isTemplateConfirmOpen"
    class="pb-tc"
    data-testid="template-confirm"
    @click.self="store.cancelTemplate()"
  >
    <div
      ref="box"
      class="pb-tc__box"
      role="dialog"
      aria-modal="true"
      aria-labelledby="pb-tc-title"
      aria-describedby="pb-tc-body"
      tabindex="-1"
      @keydown="onKeydown"
    >
      <h2 id="pb-tc-title" class="pb-tc__title">{{ s('confirmReplaceTitle', 'Replace draft with this template?') }}</h2>
      <p id="pb-tc-body" class="pb-tc__body">
        {{ s('confirmReplaceBody', 'This replaces your current draft layout. The published page will not change until you Publish. Unsaved draft changes will be replaced.') }}
      </p>

      <p v-if="store.actionError" class="pb-tc__error" role="alert">{{ store.actionError.message }}</p>

      <div class="pb-tc__actions">
        <button type="button" class="pb-tc__cancel" data-testid="confirm-cancel" @click="store.cancelTemplate()">
          {{ s('confirmCancel', 'Cancel / Keep current draft') }}
        </button>
        <button
          type="button"
          class="pb-tc__apply"
          data-testid="confirm-apply"
          :disabled="store.actionPending"
          :aria-busy="store.actionPending"
          @click="store.confirmTemplate()"
        >
          {{ store.actionPending ? s('applying', 'Applying…') : s('confirmReplace', 'Replace draft with this template') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pb-tc {
  position: fixed;
  inset: 0;
  /* PB-TEMPLATE-REGISTRY-2-H1 — the nested confirm sits above its parent modal surface.
     (Scoped inside `.pb-rb`, so it also resolves above the modal content locally.) */
  z-index: var(--pb-ui-z-modal-nested);
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.5);
  padding: 16px;
}
.pb-tc__box {
  width: min(460px, 100%);
  background: var(--pb-surface, #fff);
  border-radius: 10px;
  padding: 20px;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.3);
  outline: none;
}
.pb-tc__title { margin: 0 0 8px; font-size: 1.05rem; }
.pb-tc__body { margin: 0 0 12px; line-height: 1.5; overflow-wrap: anywhere; }
.pb-tc__error { margin: 0 0 12px; color: #b00020; }
.pb-tc__actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
.pb-tc__cancel { padding: 8px 14px; cursor: pointer; }
.pb-tc__apply { padding: 8px 14px; cursor: pointer; font-weight: 600; }
.pb-tc__apply:disabled { opacity: 0.6; cursor: not-allowed; }
.pb-tc :focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
@media (prefers-reduced-motion: reduce) { .pb-tc * { transition: none !important; animation: none !important; } }
</style>
