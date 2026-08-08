<script setup lang="ts">
// Phase 20 — "Navigator" sidebar panel. Reuses the existing structure-editor
// navigator (tree of sections/blocks with select/hover) unchanged.
import EditorNavigator from '../../../editor/components/navigator/EditorNavigator.vue'
</script>

<template>
  <div class="pb-live-builder-panel pb-live-builder-panel--navigator">
    <EditorNavigator />
  </div>
</template>
