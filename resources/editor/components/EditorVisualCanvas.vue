<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useEditorStore } from '../stores/editor'
import OverlayLayer from './overlays/OverlayLayer.vue'
import DragLayer from './drag/DragLayer.vue'
import PbNotice from '../../shared-ui/PbNotice.vue'

const store = useEditorStore()
const viewport = ref<HTMLElement | null>(null)
const host = ref<HTMLElement | null>(null)

function markers(): HTMLElement[] {
  return host.value ? Array.from(host.value.querySelectorAll<HTMLElement>('[data-pb-component-id]')) : []
}

function closestMarker(target: EventTarget | null): HTMLElement | null {
  return (target as HTMLElement | null)?.closest<HTMLElement>('[data-pb-component-id]') ?? null
}

function onClick(e: MouseEvent) {
  const el = closestMarker(e.target)
  if (!el) {
    store.selectComponent(null)
    return
  }
  e.stopPropagation()
  const id = el.dataset.pbComponentId ?? null
  store.selectComponent(store.selectedComponentId === id ? null : id)
}

function onOver(e: MouseEvent) {
  store.hoverComponent(closestMarker(e.target)?.dataset.pbComponentId ?? null)
}

// Rendered HTML lives outside Vue's reactivity (v-html). The base box class
// (cursor affordance) is applied imperatively; selection/hover *highlighting*
// is now drawn by the geometry-backed OverlayLayer, not by mutating markers.
function decorate() {
  for (const el of markers()) el.classList.add('pb-editor-visual-box')
}

// After a preview swap the marker set changes wholesale — re-tag and let the
// geometry engine reconcile + re-measure so overlays follow the new DOM. The
// engine is attached once (binds observers + scroll/resize); later swaps only
// re-scan the markers.
const attached = ref(false)
function syncCanvas() {
  decorate()
  if (!viewport.value || !host.value) return
  if (attached.value) {
    store.syncGeometry()
  } else {
    store.attachGeometry(viewport.value, host.value)
    store.attachDrag(viewport.value)
    attached.value = true
  }
}

watch(
  () => store.previewHtml,
  async () => {
    await nextTick()
    syncCanvas()
  },
)

onMounted(() => {
  if (store.previewHtml) {
    void nextTick().then(syncCanvas)
  } else if (!store.previewLoading) {
    store.schedulePreview(0)
  }
})

onBeforeUnmount(() => {
  store.detachDrag()
  store.detachGeometry()
  attached.value = false
})
</script>

<template>
  <div ref="viewport" class="pb-editor-visual-canvas">
    <div v-if="store.previewLoading" class="pb-editor-preview-loading">Rendering preview…</div>
    <PbNotice v-if="store.previewError" variant="danger" emphasis="solid" class="pb-editor-preview-error">{{ store.previewError }}</PbNotice>

    <div
      ref="host"
      class="pb-editor-visual-html"
      @click="onClick"
      @mouseover="onOver"
      @mouseleave="store.hoverComponent(null)"
      v-html="store.previewHtml"
    />

    <!-- Empty layout: a root drop zone so a section can be added visually. -->
    <div
      v-if="store.sectionCount === 0 && !store.previewLoading"
      class="pb-editor-visual-dropzone"
      :class="{ 'is-active': store.dragActive }"
    >
      Drag a section here, or use <strong>Add</strong> in the Insert panel.
    </div>

    <OverlayLayer />
    <DragLayer />
  </div>
</template>
