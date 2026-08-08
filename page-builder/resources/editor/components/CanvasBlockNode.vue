<script setup lang="ts">
import { computed } from 'vue'
import { useEditorStore } from '../stores/editor'
import type { ComponentNode } from '../types'
import CanvasNodeTree from './CanvasNodeTree.vue'
import CanvasNodeToolbar from './CanvasNodeToolbar.vue'
import OutlineDropZone from './outline/OutlineDropZone.vue'

const props = defineProps<{ node: ComponentNode }>()
const store = useEditorStore()

const selected = computed(() => store.selectedComponentId === props.node.id)
const hovered = computed(() => store.hoveredComponentId === props.node.id)
const collapsed = computed(() => store.isCollapsed(props.node.id))
const isDragSource = computed(() => store.dragState?.subject.id === props.node.id)
// A container block declares data.blocks as an array (even when empty); only
// those expose an inside drop zone.
const isContainer = computed(() => Array.isArray(props.node.data?.blocks))

function select() {
  store.selectComponent(selected.value ? null : props.node.id || null)
}
function onContextMenu(e: MouseEvent) {
  store.openContextMenu(props.node.id, e.clientX, e.clientY)
}

function onHandleDown(e: PointerEvent) {
  e.stopPropagation()
  e.preventDefault()
  try {
    ;(e.currentTarget as HTMLElement)?.setPointerCapture?.(e.pointerId)
  } catch {
    // setPointerCapture can throw if the pointer is already released; ignore.
  }
  store.beginOutlineDrag(props.node, e)
}
</script>

<template>
  <div
    class="pb-editor-block-node pb-editor-depth"
    :class="{
      'pb-editor-node-selected': selected,
      'pb-editor-node-hovered': hovered,
      'pb-editor-node-disabled': node.disabled,
      'pb-editor-node-collapsed': collapsed,
      'pb-editor-node-dragging': isDragSource,
    }"
    :style="{ '--pb-depth': node.depth }"
    role="button"
    tabindex="0"
    @click.stop="select"
    @keydown.enter.stop="select"
    @mouseenter.stop="store.hoverComponent(node.id)"
    @mouseleave.stop="store.hoverComponent(null)"
    @contextmenu.prevent.stop="onContextMenu"
  >
    <CanvasNodeToolbar v-show="selected || hovered" :node="node" />

    <div class="pb-editor-node-header">
      <button
        type="button"
        class="pb-editor-node-handle"
        title="Drag to move"
        aria-label="Drag to move"
        @pointerdown="onHandleDown"
        @click.stop
      >
        ⠿
      </button>
      <span class="pb-editor-node-kind">Block</span>
      <span class="pb-editor-node-type">{{ node.type }}</span>
      <span v-if="node.disabled" class="pb-editor-node-flag">Disabled</span>
    </div>
    <div class="pb-editor-node-meta">
      <code>{{ node.id }}</code>
      <span v-if="node.children.length">{{ node.children.length }} child block(s)</span>
      <span v-if="collapsed && node.children.length" class="pb-editor-node-collapsed-badge">collapsed</span>
    </div>

    <div v-if="node.children.length && !collapsed" class="pb-editor-node-children">
      <CanvasNodeTree :nodes="node.children" :parent-id="node.id" />
    </div>
    <!-- Empty container block: an inside drop zone so a block can land in it. -->
    <OutlineDropZone
      v-else-if="isContainer && !collapsed"
      :parent-id="node.id"
      :index="0"
      kind="inside"
    />
  </div>
</template>
