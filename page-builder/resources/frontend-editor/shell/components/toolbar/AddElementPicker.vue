<script setup lang="ts">
// Phase 26.3.10 — "Add Element" component picker, anchored below the selected
// container's toolbar. Renders the SAME ComponentPicker the Navigator "Add
// Inside" flow uses and commits through toolbar.chooseAddElement →
// editor.insertComponent (one guarded insertion path, one undo snapshot).
// Candidates are nesting-policy filtered for the selected container's type.
import { useToolbarStore } from '../../../toolbar/ToolbarManager'
import ComponentPicker from '../../../../editor/components/navigator/ComponentPicker.vue'

const toolbar = useToolbarStore()
</script>

<template>
  <div v-if="toolbar.addElementPickerOpen" class="pb-live-toolbar__addel-popup">
    <ComponentPicker
      :candidates="toolbar.addElementCandidates"
      title="Add element"
      @pick="toolbar.chooseAddElement"
      @close="toolbar.closeAddElementPicker"
    />
  </div>
</template>
