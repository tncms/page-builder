<script setup lang="ts">
// Phase 14 — Drag layer.
//
// The surface that hosts the drag-only visuals over the visual canvas: the
// insertion marker (positioned in viewport-local coordinates, like the overlay
// layer) and the ghost (fixed to the window). Both are pointer-transparent. The
// layer is inert until a drag is active, so it costs nothing at rest.
import { useEditorStore } from '../../stores/editor'
import InsertionMarker from './InsertionMarker.vue'
import DragGhost from './DragGhost.vue'

const store = useEditorStore()
</script>

<template>
  <div class="pb-drag-layer" :class="{ 'pb-drag-layer--active': store.dragActive }" data-pb-drag-layer>
    <InsertionMarker />
  </div>
  <DragGhost />
</template>
