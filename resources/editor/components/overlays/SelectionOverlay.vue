<script setup lang="ts">
// Phase 13 — Selection overlay.
//
// Outlines the selected component using the GeometryManager rect exposed by the
// store (store.selectionRect). No getBoundingClientRect here — geometry is the
// single source of truth.
import { computed } from 'vue'
import { useEditorStore } from '../../stores/editor'

const store = useEditorStore()

const style = computed(() => {
  const r = store.selectionRect
  if (!r) return null
  return {
    transform: `translate(${Math.round(r.left)}px, ${Math.round(r.top)}px)`,
    width: `${Math.round(r.width)}px`,
    height: `${Math.round(r.height)}px`,
  }
})
</script>

<template>
  <div v-if="style" class="pb-overlay-box pb-overlay-box--selected" :style="style" />
</template>
