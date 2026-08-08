<script setup lang="ts">
// Phase 13 — Overlay layer.
//
// The single overlay surface stacked over the visual canvas viewport. It owns
// the selection/hover boxes, their labels, and the toolbar — every one of them
// positioned from GeometryManager rects (via the store), never from the DOM.
// The layer itself is pointer-transparent so canvas clicks still reach the
// rendered markers; only the toolbar opts back into pointer events.
import { computed } from 'vue'
import { useEditorStore } from '../../stores/editor'
import SelectionOverlay from './SelectionOverlay.vue'
import HoverOverlay from './HoverOverlay.vue'
import ComponentLabel from './ComponentLabel.vue'
import OverlayToolbar from './OverlayToolbar.vue'

const store = useEditorStore()

const selectionLabel = computed(() => store.labelFor(store.selectedComponentId))
const hoverLabel = computed(() => store.labelFor(store.hoveredComponentId))
</script>

<template>
  <div class="pb-overlay-layer" data-pb-overlay-layer>
    <HoverOverlay />
    <ComponentLabel
      v-if="store.hoverRect && hoverLabel"
      :rect="store.hoverRect"
      :label="hoverLabel"
      variant="hover"
    />

    <SelectionOverlay />
    <ComponentLabel
      v-if="store.selectionRect && selectionLabel"
      :rect="store.selectionRect"
      :label="selectionLabel"
      variant="selected"
    />

    <OverlayToolbar />
  </div>
</template>
