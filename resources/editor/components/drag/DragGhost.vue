<script setup lang="ts">
// Phase 14 — Drag ghost.
//
// A floating, pointer-following preview of the dragged component: its icon,
// label and type. Positioned with `position: fixed` and a transform in client
// coordinates, so it tracks the cursor across the whole window (not clipped to
// the canvas) and animates cheaply on the compositor. Pointer-transparent so it
// never eats the pointerup that ends the drag.
import { computed } from 'vue'
import { useEditorStore } from '../../stores/editor'

const store = useEditorStore()

const ghost = computed(() => store.dragGhost)

const style = computed(() => {
  const g = ghost.value
  if (!g) return null
  // Offset slightly from the pointer so the cursor stays visible.
  return { transform: `translate(${Math.round(g.x + 12)}px, ${Math.round(g.y + 12)}px)` }
})
</script>

<template>
  <div v-if="store.dragActive && ghost && style" class="pb-drag-ghost" :style="style">
    <span v-if="ghost.icon" class="pb-drag-ghost__icon" v-html="ghost.icon" />
    <span class="pb-drag-ghost__label">{{ ghost.label }}</span>
    <span class="pb-drag-ghost__type">{{ ghost.type }}</span>
  </div>
</template>
