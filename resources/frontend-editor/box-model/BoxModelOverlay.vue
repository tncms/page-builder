<script setup lang="ts">
// Phase 19 — Box model overlay (spacing regions + drag handles) for the selected
// component. HOTFIX: this now renders INSIDE the `.pb-fe-overlay-viewport` host
// (positioned over the iframe, overflow:hidden), so it uses the iframe-reported
// rect verbatim as host-LOCAL coordinates — a zero offset. It no longer reads the
// iframe screen offset or tracks scroll/resize itself; the host is glued to the
// iframe and clips these regions/handles so they can never paint under the sidebar.
import { computed } from 'vue'
import { useBoxModelStore } from './BoxModelManager'
import { useInlineStore } from '../inline/InlineManager'
import { useLiveDragStore } from '../drag/LiveDragManager'
import { computeBoxes, handleCenter, type Offset } from './BoxModelCalculator'
import BoxModelHandle from './BoxModelHandle.vue'
import { SIDES, type BoxRect, type BoxType, type Side } from './types'

const box = useBoxModelStore()
const inline = useInlineStore()
const liveDrag = useLiveDragStore()

// Host-local: the overlay host origin coincides with the iframe origin, so the
// iframe-viewport rect the store holds IS already host-local. No offset to add.
const HOST_LOCAL: Offset = { top: 0, left: 0 }

const shown = computed(
  () => box.visible && !inline.active && !liveDrag.dragging && box.metrics !== null,
)

const boxes = computed(() =>
  box.metrics ? computeBoxes(box.metrics, HOST_LOCAL) : null,
)

function rectStyle(rect: BoxRect): Record<string, string> {
  return {
    top: `${rect.top}px`,
    left: `${rect.left}px`,
    width: `${rect.width}px`,
    height: `${rect.height}px`,
  }
}

function handles(type: BoxType): Array<{ side: Side; x: number; y: number; value: number }> {
  const b = boxes.value
  const metrics = box.metrics
  if (!b || !metrics) return []
  const target = type === 'margin' ? b.margin : b.padding
  return SIDES.map((side) => {
    const c = handleCenter(target, side)
    const isActive = box.dragging && box.session?.boxType === type && box.session?.side === side
    const value = isActive && box.dragValue !== null ? box.dragValue : metrics[type][side]
    return { side, x: c.x, y: c.y, value }
  })
}
</script>

<template>
  <div v-if="shown && boxes" class="pb-box-overlay" aria-hidden="false">
    <div class="pb-box-overlay__margin" :style="rectStyle(boxes.margin)"></div>
    <div class="pb-box-overlay__padding" :style="rectStyle(boxes.padding)"></div>
    <div class="pb-box-overlay__content" :style="rectStyle(boxes.content)"></div>

    <template v-for="type in (['margin', 'padding'] as const)" :key="type">
      <template v-for="h in handles(type)" :key="type + h.side">
        <BoxModelHandle :box-type="type" :side="h.side" :x="h.x" :y="h.y" />
        <span
          class="pb-box-overlay__label"
          :class="`pb-box-overlay__label--${type}`"
          :style="{ left: `${h.x}px`, top: `${h.y}px` }"
        >
          <span class="pb-box-overlay__value">{{ Math.round(h.value) }}</span>
        </span>
      </template>
    </template>
  </div>
</template>
