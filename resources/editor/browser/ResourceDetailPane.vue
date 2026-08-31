<script setup lang="ts">
// PB-TEMPLATE-REGISTRY-2-C — the shared Resource Browser preview/detail pane. Preview is
// a server-rendered iframe keyed by the selected rid (the existing Runtime Preview engine),
// so browsing/previewing NEVER mutates the draft, history, token, or database. It offers a
// single materialization action per kind: Insert (Pattern) delegates to the existing
// insert-pattern command; Use-template (Template) opens the explicit two-stage confirmation.
// An unavailable/locked resource cannot act. All resource metadata is escaped text (no
// v-html sink); all chrome is Admin-UI-locale strings from the server catalog.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useResourceBrowserStore } from './resourceBrowserStore'
import ResourcePreview from '../inspector/ResourcePreview.vue'

const store = useResourceBrowserStore()

const region = ref<HTMLElement | null>(null)
const device = ref<'desktop' | 'tablet' | 'mobile'>('desktop')
let lastFocus: HTMLElement | null = null

const s = (key: string, fallback = ''): string => store.label(key, fallback)

const item = computed(() => store.selectedItem)
const isTemplate = computed(() => item.value?.kind === 'template')
const canAct = computed(() => store.canActOnSelected)
const previewSrc = computed(() => (item.value ? store.previewUrl(item.value.rid, device.value) : ''))

const reason = computed<string>(() => {
  const it = item.value
  if (!it || canAct.value) return ''
  if (it.locked) return s('lockedReason', 'This resource requires an upgrade.')
  return s('unavailableReason', 'This resource is unavailable.')
})

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.stopPropagation()
    store.closeDetail()
  }
}

onMounted(() => {
  lastFocus = (document.activeElement as HTMLElement) ?? null
  region.value?.focus()
})
onBeforeUnmount(() => {
  if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus()
})
</script>

<template>
  <section
    v-if="item"
    ref="region"
    class="pb-rd"
    role="region"
    tabindex="-1"
    :aria-label="`${s('preview', 'Preview')}: ${item.name}`"
    data-testid="resource-detail-pane"
    @keydown="onKeydown"
  >
    <header class="pb-rd__head">
      <button type="button" class="pb-rd__back" :aria-label="s('back', 'Back to list')" data-testid="detail-close" @click="store.closeDetail()">
        &lsaquo; {{ s('back', 'Back') }}
      </button>
      <h3 class="pb-rd__title">{{ item.name }}</h3>
    </header>

    <div class="pb-rd__body">
      <div class="pb-rd__preview">
        <ResourcePreview :src="previewSrc" :name="item.name" :device="device" @update:device="device = $event" />
      </div>

      <dl class="pb-rd__meta">
        <div v-if="item.description"><dt class="pb-rd__sr">{{ s('description', 'Description') }}</dt><dd>{{ item.description }}</dd></div>
        <div><dt>{{ s('source', 'Source') }}</dt><dd>{{ item.source }}</dd></div>
        <div><dt>{{ s('category', 'Category') }}</dt><dd>{{ item.category }}</dd></div>
      </dl>
    </div>

    <p v-if="reason" class="pb-rd__reason" data-testid="detail-reason">{{ reason }}</p>

    <p v-if="store.actionError" class="pb-rd__error" role="alert" data-testid="detail-error">{{ store.actionError.message }}</p>

    <footer class="pb-rd__foot">
      <button
        v-if="isTemplate"
        type="button"
        class="pb-rd__act"
        data-testid="detail-use-template"
        :disabled="!canAct || store.actionPending"
        :aria-busy="store.actionPending"
        @click="store.requestTemplate()"
      >
        {{ s('useTemplate', 'Use this template') }}
      </button>
      <button
        v-else
        type="button"
        class="pb-rd__act"
        data-testid="detail-insert"
        :disabled="!canAct || store.actionPending"
        :aria-busy="store.actionPending"
        @click="store.insertSelected()"
      >
        {{ store.actionPending ? s('inserting', 'Inserting…') : s('insert', 'Insert') }}
      </button>
    </footer>
  </section>
</template>

<style scoped>
.pb-rd {
  position: absolute;
  inset: 0;
  z-index: 2;
  display: flex;
  flex-direction: column;
  background: var(--pb-surface, #fff);
  outline: none;
}
.pb-rd__head { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-bottom: 1px solid rgba(0, 0, 0, 0.1); }
.pb-rd__back { border: 0; background: none; cursor: pointer; color: inherit; font: inherit; }
.pb-rd__title { flex: 1; margin: 0; font-size: 1rem; overflow-wrap: anywhere; }
.pb-rd__body { flex: 1 1 auto; overflow: auto; padding: 12px 16px; display: flex; flex-direction: column; gap: 12px; }
.pb-rd__preview { min-height: 0; }
.pb-rd__meta { margin: 0; display: grid; gap: 6px; }
.pb-rd__meta div { display: flex; gap: 8px; }
.pb-rd__meta dt { font-weight: 600; opacity: 0.7; margin: 0; }
.pb-rd__meta dd { margin: 0; overflow-wrap: anywhere; }
.pb-rd__reason { margin: 0; padding: 0 16px 8px; opacity: 0.85; }
.pb-rd__error { margin: 0; padding: 0 16px 8px; color: #b00020; }
.pb-rd__foot { padding: 12px 16px; border-top: 1px solid rgba(0, 0, 0, 0.1); display: flex; justify-content: flex-end; }
.pb-rd__act { padding: 8px 16px; cursor: pointer; }
.pb-rd__act:disabled { opacity: 0.5; cursor: not-allowed; }
.pb-rd__sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
.pb-rd :focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
@media (max-width: 640px) { .pb-rd__body { padding: 8px 12px; } }
@media (prefers-reduced-motion: reduce) { .pb-rd * { transition: none !important; animation: none !important; } }
</style>
