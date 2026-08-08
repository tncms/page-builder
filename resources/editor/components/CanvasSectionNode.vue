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

function select() {
  store.selectComponent(selected.value ? null : props.node.id || null)
}
function onContextMenu(e: MouseEvent) {
  store.openContextMenu(props.node.id, e.clientX, e.clientY)
}

// The handle is the only thing that starts a drag — a plain click still selects.
// preventDefault stops text selection; capturing keeps the gesture alive as the
// pointer leaves the handle.
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
    class="pb-editor-section-node pb-editor-depth"
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
      <span class="pb-editor-node-kind">Section</span>
      <span class="pb-editor-node-type">{{ node.type }}</span>
      <span v-if="node.disabled" class="pb-editor-node-flag">Disabled</span>
    </div>
    <div class="pb-editor-node-meta">
      <code>{{ node.id }}</code>
      <span>{{ node.children.length }} block(s)</span>
      <span v-if="collapsed && node.children.length" class="pb-editor-node-collapsed-badge">collapsed</span>
    </div>

    <div v-if="node.children.length && !collapsed" class="pb-editor-node-children">
      <CanvasNodeTree :nodes="node.children" :parent-id="node.id" />
    </div>
    <!-- Empty section: an inside drop zone so a block can land in it. -->
    <OutlineDropZone
      v-else-if="!collapsed"
      :parent-id="node.id"
      :index="0"
      kind="inside"
    />
  </div>
</template>
