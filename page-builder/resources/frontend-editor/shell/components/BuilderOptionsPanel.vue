<script setup lang="ts">
// Phase 20 — "Options" sidebar panel: the Property Inspector for the selected
// component. Reuses the structure-editor PropertyInspector (same store, schema,
// commit + live-patch bridge). Shows a hint when nothing is selected.
import { useEditorStore } from '../../../editor/stores/editor'
import PropertyInspector from '../../../editor/components/inspector/PropertyInspector.vue'

const store = useEditorStore()
</script>

<template>
  <div class="pb-live-builder-panel pb-live-builder-panel--options">
    <PropertyInspector v-if="store.selectedComponentId" />
    <p v-else class="pb-live-builder-panel__hint">
      Select a component on the page to edit its options.
    </p>
  </div>
</template>
