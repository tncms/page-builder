<script setup lang="ts">
// Phase 26.47F — Builder Resource Inspector. Preview-first, insert-second: a
// resource opens here (never inserts blindly). It reuses the Runtime Preview
// engine (ResourcePreview), shows registry metadata (no RID), respects Phase 26.45
// availability, and inserts through the EXISTING pipeline via an @insert emit — it
// owns no insertion logic, no commands, no runtime. Keyboard: Esc closes, Enter
// inserts (when available), ←/→ navigate within the current palette group.
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import type { BuilderResourceItem } from '../types'
import ResourcePreview from './ResourcePreview.vue'
import ResourceMetadata from './ResourceMetadata.vue'
import ResourceBadges from './ResourceBadges.vue'
import ResourceCompatibility from './ResourceCompatibility.vue'
import ResourceActions from './ResourceActions.vue'

const props = defineProps<{ items: BuilderResourceItem[]; index: number; previewBase: string }>()
const emit = defineEmits<{ close: []; insert: [BuilderResourceItem]; 'update:index': [number] }>()

const box = ref<HTMLElement | null>(null)
const device = ref<'desktop' | 'tablet' | 'mobile'>('desktop')
let lastFocus: HTMLElement | null = null

const current = computed<BuilderResourceItem | null>(() => props.items[props.index] ?? null)
const canInsert = computed(() => !!current.value && !current.value.locked)
const hasPrev = computed(() => props.index > 0)
const hasNext = computed(() => props.index < props.items.length - 1)

const previewSrc = computed(() => {
  const rid = current.value?.rid
  if (!props.previewBase || !rid) return ''
  const sep = props.previewBase.includes('?') ? '&' : '?'
  return `${props.previewBase}${sep}source=rid&ref=${encodeURIComponent(rid)}&device=${device.value}`
})

function goPrev() {
  if (hasPrev.value) emit('update:index', props.index - 1)
}
function goNext() {
  if (hasNext.value) emit('update:index', props.index + 1)
}
function doInsert() {
  if (canInsert.value && current.value) emit('insert', current.value)
}

function focusables(): HTMLElement[] {
  if (!box.value) return []
  return Array.from(
    box.value.querySelectorAll<HTMLElement>(
      'button:not([disabled]), a[href], input, iframe, [tabindex]:not([tabindex="-1"])',
    ),
  )
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    event.preventDefault()
    emit('close')
    return
  }
  if (event.key === 'ArrowLeft') {
    event.preventDefault()
    goPrev()
    return
  }
  if (event.key === 'ArrowRight') {
    event.preventDefault()
    goNext()
    return
  }
  if (event.key === 'Enter') {
    // Let a focused button act on its own; otherwise Enter inserts (Rule 11).
    if ((event.target as HTMLElement)?.tagName === 'BUTTON') return
    if (canInsert.value) {
      event.preventDefault()
      doInsert()
    }
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
    v-if="current"
    class="pb-ri"
    role="dialog"
    aria-modal="true"
    :aria-label="`Design resource: ${current.name}`"
    data-testid="builder-resource-inspector"
    @click.self="$emit('close')"
  >
    <div ref="box" class="pb-ri__drawer" tabindex="-1" @keydown="onKeydown">
      <header class="pb-ri__head">
        <div class="pb-ri__nav" role="group" aria-label="Browse resources">
          <button type="button" class="pb-ri__icon" :disabled="!hasPrev" aria-label="Previous resource" data-testid="prev" @click="goPrev">‹</button>
          <button type="button" class="pb-ri__icon" :disabled="!hasNext" aria-label="Next resource" data-testid="next" @click="goNext">›</button>
        </div>
        <h2 class="pb-ri__title" data-testid="inspector-title">{{ current.name }}</h2>
        <button type="button" class="pb-ri__icon pb-ri__close" aria-label="Close inspector" data-testid="close" @click="$emit('close')">×</button>
      </header>

      <div class="pb-ri__body">
        <div class="pb-ri__preview">
          <ResourcePreview :src="previewSrc" :name="current.name" :device="device" @update:device="device = $event" />
        </div>
        <div class="pb-ri__meta">
          <ResourceBadges :kind="current.kind" :source-group="current.group" />
          <ResourceCompatibility :compatibility="current.compatibility" />
          <ResourceMetadata :item="current" />
        </div>
      </div>

      <footer class="pb-ri__foot">
        <ResourceActions
          :locked="current.locked"
          :required-capability="current.requiredCapability"
          @cancel="$emit('close')"
          @insert="doInsert"
        />
      </footer>
    </div>
  </div>
</template>

<style scoped>
.pb-ri {
  position: fixed; inset: 0; z-index: 2147483300; display: flex; justify-content: flex-end;
  background: rgba(6, 8, 12, 0.6);
}
.pb-ri__drawer {
  width: min(520px, 100%); height: 100%; display: flex; flex-direction: column;
  background: var(--pb-editor-surface, #171a21); border-left: 1px solid var(--pb-border, #262b36);
  box-shadow: -18px 0 48px rgba(0, 0, 0, 0.4); outline: none;
}
.pb-ri__head { display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; border-bottom: 1px solid var(--pb-border, #262b36); }
.pb-ri__nav { display: flex; gap: 0.25rem; }
.pb-ri__title { flex: 1; margin: 0; font-size: 1rem; font-weight: 700; color: var(--pb-editor-text, #e7eaf0); }
.pb-ri__icon {
  border: 1px solid var(--pb-border, #262b36); background: var(--pb-panel, #0f1115); color: var(--pb-editor-text, #e7eaf0);
  width: 1.9rem; height: 1.9rem; border-radius: 8px; font-size: 1.1rem; line-height: 1; cursor: pointer;
}
.pb-ri__icon:disabled { opacity: 0.4; cursor: default; }
.pb-ri__body { flex: 1; min-height: 0; overflow: auto; padding: 1rem; display: flex; flex-direction: column; gap: 1rem; }
.pb-ri__preview { min-height: 0; }
.pb-ri__meta { display: flex; flex-direction: column; gap: 0.75rem; }
.pb-ri__foot { padding: 0.85rem 1rem; border-top: 1px solid var(--pb-border, #262b36); }

/* Rule 13 — responsive: full-screen drawer on small viewports. */
@media (max-width: 640px) {
  .pb-ri { justify-content: stretch; }
  .pb-ri__drawer { width: 100%; border-left: 0; }
}
</style>
