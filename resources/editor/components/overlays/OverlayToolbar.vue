<script setup lang="ts">
// Phase 13 — Toolbar overlay.
//
// The floating action toolbar for the selected component. Its position is
// derived purely from geometry (store.selectionRect) — it pins to the top-right
// of the selection and sits just above it. No DOM queries; it moves whenever
// geometry refreshes because selectionRect recomputes on geometryVersion.
import { computed } from 'vue'
import { useEditorStore } from '../../stores/editor'

const store = useEditorStore()

const id = computed(() => store.selectedComponentId)

const style = computed(() => {
  const r = store.selectionRect
  if (!r) return null
  // Anchor at the selection's top-right, lifted above the box.
  return {
    transform: `translate(${Math.round(r.right)}px, ${Math.round(r.top)}px)`,
  }
})

// Execute an action against the toolbar's component id. Must run `fn` directly:
// a helper that RETURNS a handler is silently discarded by Vue's inline-handler
// compilation (`@click="run(fn)"` becomes `$event => run(fn)`), which made every
// toolbar button a no-op. Propagation is stopped via the `.stop` modifier.
function run(fn: (componentId: string) => void) {
  if (id.value) fn(id.value)
}

// The drag handle is the ONLY thing that begins a drag — a plain click/select
// on the component never does. Consume the pointerdown so it doesn't bubble to
// the canvas as a selection.
function onHandleDown(e: PointerEvent) {
  e.stopPropagation()
  if (id.value) store.beginDrag(id.value, e)
}
</script>

<template>
  <div v-if="style && id" class="pb-overlay-toolbar" :style="style">
    <button
      type="button"
      class="pb-overlay-toolbar__handle"
      title="Drag to move"
      aria-label="Drag to move"
      @pointerdown="onHandleDown"
    >
      <span class="pb-overlay-toolbar__handle-grip" aria-hidden="true">⠿</span>
      <span class="pb-overlay-toolbar__handle-text">Drag</span>
    </button>
    <button type="button" title="Duplicate" @click.stop="run((c) => store.duplicateComponent(c))">⧉</button>
    <button type="button" title="Copy" @click.stop="run((c) => store.copyComponent(c))">⧉c</button>
    <button type="button" title="Cut" @click.stop="run((c) => store.cutComponent(c))">✂</button>
    <button
      type="button"
      class="pb-overlay-toolbar__danger"
      title="Delete"
      @click.stop="run((c) => store.deleteComponent(c))"
    >
      ✕
    </button>
  </div>
</template>
