<script setup lang="ts">
import { computed } from 'vue'
import { useEditorStore } from '../stores/editor'
import type { ComponentNode } from '../types'

const props = defineProps<{ node: ComponentNode }>()
const store = useEditorStore()

const hasChildren = computed(() => props.node.children.length > 0)
const canContain = computed(
  () => props.node.kind === 'section' || Array.isArray((props.node.data as { blocks?: unknown }).blocks),
)
const collapsed = computed(() => store.isCollapsed(props.node.id))

// Execute an action, stopping propagation via the `.stop` click modifier. Must
// run `fn` directly: a helper that RETURNS a handler is silently discarded by
// Vue (`@click="run(fn)"` compiles to `$event => run(fn)`), which turned every
// button here into a no-op.
function run(fn: () => void) {
  fn()
}
</script>

<template>
  <div class="pb-editor-node-toolbar" @click.stop>
    <button
      v-if="hasChildren"
      type="button"
      class="pb-editor-node-action"
      :title="collapsed ? 'Expand' : 'Collapse'"
      @click.stop="run(() => store.toggleCollapse(node.id))"
    >
      {{ collapsed ? '▸' : '▾' }}
    </button>
    <button type="button" class="pb-editor-node-action" title="Duplicate" @click.stop="run(() => store.duplicateComponent(node.id))">⧉</button>
    <button type="button" class="pb-editor-node-action" title="Copy" @click.stop="run(() => store.copyComponent(node.id))">⧉c</button>
    <button type="button" class="pb-editor-node-action" title="Cut" @click.stop="run(() => store.cutComponent(node.id))">✂</button>
    <button
      v-if="canContain && store.hasClipboard"
      type="button"
      class="pb-editor-node-action"
      title="Paste inside"
      @click.stop="run(() => store.pasteInto(node.id))"
    >
      ⤵
    </button>
    <!-- Phase 26.48 — Save as Design (external persistence; never an edit command). -->
    <button
      v-if="node.kind === 'section' || node.kind === 'block'"
      type="button"
      class="pb-editor-node-action"
      title="Save as Design"
      data-testid="node-save-as-design"
      @click.stop="run(() => store.openCapture(node.id))"
    >
      ⭳
    </button>
    <button type="button" class="pb-editor-node-action pb-editor-node-action--danger" title="Delete" @click.stop="run(() => store.deleteComponent(node.id))">✕</button>
  </div>
</template>
