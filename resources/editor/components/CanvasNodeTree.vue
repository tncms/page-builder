<script setup lang="ts">
// Recursively renders a list of component nodes, dispatching each to the section
// or block presenter. Section/block presenters render their own children back
// through this component — the recursion lives here (Phase 10, read-only).
//
// Phase 14.4: interleaves outline drop zones — one before the first node and one
// after each node — so a drag can land at any sibling index. Zones carry this
// list's parent (null at the root) and the index they represent.
import type { ComponentNode } from '../types'
import CanvasSectionNode from './CanvasSectionNode.vue'
import CanvasBlockNode from './CanvasBlockNode.vue'
import OutlineDropZone from './outline/OutlineDropZone.vue'

withDefaults(defineProps<{ nodes: ComponentNode[]; parentId?: string | null }>(), { parentId: null })
</script>

<template>
  <OutlineDropZone :parent-id="parentId ?? null" :index="0" />
  <template v-for="(node, i) in nodes" :key="node.id || node.type">
    <CanvasSectionNode v-if="node.kind === 'section'" :node="node" />
    <CanvasBlockNode v-else :node="node" />
    <OutlineDropZone :parent-id="parentId ?? null" :index="i + 1" />
  </template>
</template>
