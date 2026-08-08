<script setup lang="ts">
// Phase 22.1 — Live Builder drag visuals. Renders the pointer-following ghost
// (shell-window coordinates) and the insertion marker (iframe-viewport rect +
// the live iframe screen offset, exactly like the box-model / inline overlays).
// A single coordinate system: everything from the iframe is iframe-relative and
// gets the frame's screen offset added here. Both visuals are pointer-transparent
// so they never eat the pointerup that ends the drag.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useLiveDragStore } from '../../../drag/LiveDragManager'
import { iframeRectToShellRect } from '../../../geometry/IframeCoordinates'

const props = defineProps<{ frameEl: HTMLIFrameElement | null }>()

const drag = useLiveDragStore()
const tick = ref(0)

function reposition(): void {
  // Bump the tick so the marker style recomputes after scroll/resize (rects
  // aren't reactive); the shell offset itself is read live via the helper below.
  tick.value += 1
}

const ghostStyle = computed<Record<string, string> | null>(() => {
  const g = drag.ghost
  if (!g) return null
  // Offset from the pointer so the cursor stays visible.
  return { transform: `translate(${Math.round(g.x + 12)}px, ${Math.round(g.y + 12)}px)` }
})

const markerStyle = computed<Record<string, string> | null>(() => {
  void tick.value // recompute after scroll/resize (rects aren't reactive)
  const rect = drag.markerRect
  const edge = drag.markerEdge
  if (!rect || !edge) return null

  // Single conversion point: iframe-viewport rect → shell coords via the helper.
  const shell = iframeRectToShellRect(props.frameEl, rect)
  const top = shell.top
  const left = shell.left

  if (edge === 'inside') {
    return {
      transform: `translate(${Math.round(left)}px, ${Math.round(top)}px)`,
      width: `${Math.round(rect.width)}px`,
      height: `${Math.round(rect.height)}px`,
    }
  }
  const y = edge === 'after' ? top + rect.height : top
  return {
    transform: `translate(${Math.round(left)}px, ${Math.round(y)}px)`,
    width: `${Math.round(rect.width)}px`,
  }
})

const markerClass = computed(() => {
  const shape = drag.markerEdge === 'inside' ? 'box' : 'line'
  const state = drag.valid ? 'valid' : 'invalid'
  return `pb-live-drag-marker--${shape} pb-live-drag-marker--${state}`
})

const onWindow = () => reposition()
onMounted(() => {
  reposition()
  window.addEventListener('scroll', onWindow, { passive: true })
  window.addEventListener('resize', onWindow)
})
onBeforeUnmount(() => {
  window.removeEventListener('scroll', onWindow)
  window.removeEventListener('resize', onWindow)
})
</script>

<template>
  <template v-if="drag.dragging">
    <div
      v-if="markerStyle"
      class="pb-live-drag-marker"
      :class="markerClass"
      :style="markerStyle"
      data-pb-live-drag-marker
    />
    <div v-if="ghostStyle" class="pb-live-drag-ghost" :style="ghostStyle">
      <span v-if="drag.ghostIcon" class="pb-live-drag-ghost__icon" v-html="drag.ghostIcon" />
      <span class="pb-live-drag-ghost__label">{{ drag.ghostLabel }}</span>
      <span class="pb-live-drag-ghost__type">{{ drag.ghostType }}</span>
    </div>
  </template>
</template>
