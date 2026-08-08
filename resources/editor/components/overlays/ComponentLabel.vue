<script setup lang="ts">
// Phase 13 — Component label overlay.
//
// Presentational: draws a small type/kind tag at the top-left of a geometry
// rect. Position comes entirely from the rect prop (from GeometryManager); this
// component never touches the DOM.
import { computed } from 'vue'
import type { GeometryRect } from '../../geometry'

const props = defineProps<{
  rect: GeometryRect
  label: string
  variant?: 'selected' | 'hover'
}>()

const style = computed(() => ({
  transform: `translate(${Math.round(props.rect.left)}px, ${Math.round(props.rect.top)}px)`,
}))
</script>

<template>
  <div
    class="pb-overlay-label"
    :class="`pb-overlay-label--${variant ?? 'selected'}`"
    :style="style"
  >
    {{ label }}
  </div>
</template>
