<script setup lang="ts">
// Phase 22 — Compact parent breadcrumb for the in-canvas toolbar. Shows the
// selection's ancestors (root → parent); clicking one selects it. Purely a
// navigation aid — it derives from the editor store's selectedPath, never the DOM.
import { useEditorStore } from '../../../../editor/stores/editor'
import { useToolbarStore } from '../../../toolbar/ToolbarManager'
import type { PathItem } from '../../../../editor/utils/layout'

const editor = useEditorStore()
const toolbar = useToolbarStore()

function labelOf(item: PathItem): string {
  const def = editor.definitionOf(item.kind, item.type)
  const fromDef = (def?.label as string) || (def?.name as string) || ''
  if (fromDef) return fromDef
  return item.type ? item.type.charAt(0).toUpperCase() + item.type.slice(1) : item.kind
}

function select(item: PathItem): void {
  editor.selectComponent(item.id)
}
</script>

<template>
  <nav
    v-if="toolbar.breadcrumb.length"
    class="pb-live-toolbar__breadcrumb"
    aria-label="Component ancestors"
  >
    <button
      v-for="item in toolbar.breadcrumb"
      :key="item.id"
      type="button"
      class="pb-live-toolbar__breadcrumb-item"
      :title="`Select ${labelOf(item)}`"
      :aria-label="`Select ${labelOf(item)}`"
      @click="select(item)"
    >
      {{ labelOf(item) }}
    </button>
  </nav>
</template>
