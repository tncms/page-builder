<script setup lang="ts">
// Phase 14 — Insertion marker.
//
// Draws where the dragged component would land: a line at the top ("before") or
// bottom ("after") of the reference component, or a filled outline over it
// ("inside" a container). Position comes entirely from the drop resolution's
// geometry rect (viewport-local) — no DOM queries. Blue means a legal drop, red
// an illegal one, matching the required visual feedback.
import { computed } from 'vue'
import { useEditorStore } from '../../stores/editor'

const store = useEditorStore()

const resolution = computed(() => store.dragResolution)

// Reading geometryVersion keeps the marker glued to the reference rect through
// scroll/resize refreshes while a drag is held.
const rect = computed(() => (store.geometryVersion >= 0 ? resolution.value?.markerRect ?? null : null))

const style = computed(() => {
  const r = rect.value
  const res = resolution.value
  if (!r || !res || !res.edge) return null

  if (res.edge === 'inside') {
    return {
      transform: `translate(${Math.round(r.left)}px, ${Math.round(r.top)}px)`,
      width: `${Math.round(r.width)}px`,
      height: `${Math.round(r.height)}px`,
    }
  }

  const y = res.edge === 'after' ? r.bottom : r.top
  return {
    transform: `translate(${Math.round(r.left)}px, ${Math.round(y)}px)`,
    width: `${Math.round(r.width)}px`,
  }
})

const kindClass = computed(() => {
  const res = resolution.value
  if (!res) return ''
  const state = res.valid ? 'valid' : 'invalid'
  const shape = res.edge === 'inside' ? 'box' : 'line'
  return `pb-drag-marker--${shape} pb-drag-marker--${state}`
})
</script>

<template>
  <div
    v-if="store.dragActive && style"
    class="pb-drag-marker"
    :class="kindClass"
    :style="style"
    data-pb-drag-marker
  />
</template>
