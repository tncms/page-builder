<script setup lang="ts">
import { computed } from 'vue'
import { useEditorStore } from '../stores/editor'
import { findComponentNode } from '../utils/layout'

const store = useEditorStore()

const targetId = computed(() => store.contextMenu.targetId)
const target = computed(() =>
  targetId.value ? findComponentNode(store.currentPayload, targetId.value) : null,
)
const canContain = computed(() => target.value?.canContainChildren ?? false)

function run(fn: () => void) {
  fn()
  store.closeContextMenu()
}
</script>

<template>
  <div
    v-if="store.contextMenu.visible && targetId"
    class="pb-editor-context-menu"
    :style="{ left: `${store.contextMenu.x}px`, top: `${store.contextMenu.y}px` }"
    @click.stop
    @contextmenu.prevent.stop
  >
    <button class="pb-editor-context-menu__item" @click="run(() => store.duplicateComponent(targetId!))">Duplicate</button>
    <button class="pb-editor-context-menu__item" @click="run(() => store.copyComponent(targetId!))">Copy</button>
    <button class="pb-editor-context-menu__item" @click="run(() => store.cutComponent(targetId!))">Cut</button>
    <button
      v-if="canContain && store.hasClipboard"
      class="pb-editor-context-menu__item"
      @click="run(() => store.pasteInto(targetId!))"
    >
      Paste inside
    </button>
    <button
      class="pb-editor-context-menu__item pb-editor-context-menu__item--danger"
      @click="run(() => store.deleteComponent(targetId!))"
    >
      Delete
    </button>
  </div>
</template>
