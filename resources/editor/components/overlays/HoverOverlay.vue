<script setup lang="ts">
// Phase 13 — Hover overlay.
//
// Draws the hover rectangle from store.hoverRect (GeometryManager-backed). The
// store already suppresses hover while it equals the selection.
import { computed } from 'vue'
import { useEditorStore } from '../../stores/editor'

const store = useEditorStore()

const style = computed(() => {
  const r = store.hoverRect
  if (!r) return null
  return {
    transform: `translate(${Math.round(r.left)}px, ${Math.round(r.top)}px)`,
    width: `${Math.round(r.width)}px`,
    height: `${Math.round(r.height)}px`,
  }
})
</script>

<template>
  <div v-if="style" class="pb-overlay-box pb-overlay-box--hover" :style="style" />
</template>
