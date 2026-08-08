<script setup lang="ts">
// Phase 15: the right panel hosts the schema-driven Property Inspector.
// Phase 26.48: it also exposes "Save as Design" for the selected section/block —
// an external persistence action (never an edit command, never touches undo).
import { computed } from 'vue'
import { useEditorStore } from '../stores/editor'
import PropertyInspector from './inspector/PropertyInspector.vue'

const store = useEditorStore()
const canSave = computed(() => {
  const sel = store.selectedComponent
  return !!sel && (sel.kind === 'section' || sel.kind === 'block')
})

function saveAsDesign() {
  if (store.selectedComponentId) store.openCapture(store.selectedComponentId)
}
</script>

<template>
  <aside class="pb-editor-panel pb-editor-panel--right">
    <div class="pb-editor-panel__heading">
      Inspector
      <button
        v-if="canSave"
        type="button"
        class="pb-editor-panel__action"
        title="Save this section or block as a reusable design"
        data-testid="inspector-save-as-design"
        @click="saveAsDesign"
      >
        Save as Design
      </button>
    </div>
    <PropertyInspector />
  </aside>
</template>

<style scoped>
.pb-editor-panel__heading { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
.pb-editor-panel__action {
  border: 1px solid var(--pb-border, #262b36); background: var(--pb-panel, #0f1115); color: var(--pb-editor-text, #e7eaf0);
  border-radius: 8px; padding: 0.3rem 0.55rem; font: inherit; font-size: 0.72rem; cursor: pointer;
}
.pb-editor-panel__action:hover { border-color: var(--pb-accent, #6ea8fe); }
</style>
