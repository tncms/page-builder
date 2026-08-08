<script setup lang="ts">
// Phase 15 — Selection breadcrumb.
//
// Shows the path from the root section to the selected component
// ("Section › Container › Button") as clickable chips. Clicking an ancestor
// selects it and reveals it on the canvas. Hidden when nothing is selected.
import { computed } from 'vue'
import { useEditorStore } from '../../stores/editor'
import type { PathItem } from '../../utils/layout'

const store = useEditorStore()

const path = computed(() => store.selectedPath)

function labelFor(item: PathItem): string {
  const def = store.definitionOf(item.kind, item.type)
  const raw = (def?.label as string) || (def?.name as string) || item.type
  return raw.charAt(0).toUpperCase() + raw.slice(1)
}
</script>

<template>
  <nav v-if="path.length" class="pb-editor-breadcrumb" aria-label="Selected component path">
    <template v-for="(item, i) in path" :key="item.id">
      <span v-if="i > 0" class="pb-editor-breadcrumb__sep" aria-hidden="true">›</span>
      <button
        type="button"
        class="pb-editor-breadcrumb__chip"
        :class="{ 'is-current': i === path.length - 1 }"
        @click="store.focusComponent(item.id)"
      >
        {{ labelFor(item) }}
      </button>
    </template>
  </nav>
</template>
