<script setup lang="ts">
// Phase 19 — A draggable box-model handle. Drag to change one side's spacing;
// hold Shift to snap to 10px; Escape aborts. Streams the delta to the manager
// (which live-patches the iframe) and commits on release.
import { onBeforeUnmount, ref } from 'vue'
import { useBoxModelStore } from './BoxModelManager'
import type { BoxType, Side } from './types'

const props = defineProps<{ boxType: BoxType; side: Side; x: number; y: number }>()

const box = useBoxModelStore()
const dragging = ref(false)
let startX = 0
let startY = 0
let frame = 0
let pending: { dx: number; dy: number; shift: boolean } | null = null

function flush(): void {
  frame = 0
  if (pending) {
    box.updateDrag(pending.dx, pending.dy, pending.shift)
    pending = null
  }
}

function onPointerDown(event: PointerEvent): void {
  event.preventDefault()
  event.stopPropagation()
  dragging.value = true
  startX = event.clientX
  startY = event.clientY
  box.startDrag(props.boxType, props.side)
  ;(event.target as HTMLElement).setPointerCapture(event.pointerId)
}

function onPointerMove(event: PointerEvent): void {
  if (!dragging.value) return
  pending = { dx: event.clientX - startX, dy: event.clientY - startY, shift: event.shiftKey }
  if (!frame) frame = requestAnimationFrame(flush)
}

function onPointerUp(event: PointerEvent): void {
  if (!dragging.value) return
  dragging.value = false
  if (frame) {
    cancelAnimationFrame(frame)
    flush()
  }
  ;(event.target as HTMLElement).releasePointerCapture?.(event.pointerId)
  void box.endDrag()
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && dragging.value) {
    dragging.value = false
    if (frame) cancelAnimationFrame(frame)
    box.cancel()
  }
}

onBeforeUnmount(() => {
  if (frame) cancelAnimationFrame(frame)
})
</script>

<template>
  <button
    type="button"
    class="pb-box-overlay__handle"
    :class="[`pb-box-overlay__handle--${boxType}`, `pb-box-overlay__handle--${side}`, { 'is-dragging': dragging }]"
    :style="{ left: `${x}px`, top: `${y}px` }"
    :aria-label="`${boxType} ${side}`"
    @pointerdown="onPointerDown"
    @pointermove="onPointerMove"
    @pointerup="onPointerUp"
    @keydown="onKeydown"
  ></button>
</template>
