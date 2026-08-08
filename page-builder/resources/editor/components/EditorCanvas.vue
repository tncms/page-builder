<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { useEditorStore } from '../stores/editor'
import CanvasNodeTree from './CanvasNodeTree.vue'
import CanvasContextMenu from './CanvasContextMenu.vue'
import EmptyState from './EmptyState.vue'
import OutlineDropZone from './outline/OutlineDropZone.vue'
import PbNotice from '../../shared-ui/PbNotice.vue'

const store = useEditorStore()

function isEditableTarget(target: EventTarget | null): boolean {
  const el = target as HTMLElement | null
  if (!el) return false
  const tag = el.tagName
  return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable
}

function onKeydown(e: KeyboardEvent) {
  // Save (Ctrl/Cmd+S) is handled even while a field is focused: saveDraft first
  // flushes the inspector's pending edit, so the just-typed value is captured.
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
    e.preventDefault()
    void store.saveDraft()
    return
  }

  // Never hijack typing in form fields / rich-text.
  if (isEditableTarget(e.target)) return

  const key = e.key.toLowerCase()
  const mod = e.ctrlKey || e.metaKey
  const hasSelection = store.selectedComponentId !== null

  if (key === 'escape') {
    store.closeContextMenu()
    store.selectComponent(null)
    return
  }

  if (!mod) {
    if ((key === 'delete' || key === 'backspace') && hasSelection) {
      e.preventDefault()
      store.deleteSelected()
    }
    return
  }

  switch (key) {
    case 'd':
      if (hasSelection) {
        e.preventDefault()
        store.duplicateSelected()
      }
      break
    case 'c':
      if (hasSelection) {
        e.preventDefault()
        store.copySelected()
      }
      break
    case 'x':
      if (hasSelection) {
        e.preventDefault()
        store.cutSelected()
      }
      break
    case 'v':
      if (store.hasClipboard) {
        e.preventDefault()
        void store.pasteInto()
      }
      break
    case 'z':
      e.preventDefault()
      if (e.shiftKey) store.redo()
      else store.undo()
      break
    case 'y':
      e.preventDefault()
      store.redo()
      break
  }
}

function onDocumentClick() {
  if (store.contextMenu.visible) store.closeContextMenu()
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
  document.addEventListener('click', onDocumentClick)
  store.attachOutlineDrag()
})
onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
  document.removeEventListener('click', onDocumentClick)
  store.detachOutlineDrag()
})

function onBackdropClick() {
  store.selectComponent(null)
  store.closeContextMenu()
}
</script>

<template>
  <main class="pb-editor-canvas" @click="onBackdropClick">
    <div class="pb-editor-canvas__header">
      <span>{{ store.sectionCount }} section(s)</span>
      <span v-if="store.commandLoading" class="pb-editor-command-loading">Applying…</span>
      <span class="pb-editor-shortcut-hint">
        Del · Ctrl+D dup · Ctrl+C/X/V · Ctrl+Z/Y
      </span>
    </div>

    <PbNotice v-if="store.commandError" variant="danger" emphasis="solid" class="pb-editor-error">{{ store.commandError }}</PbNotice>

    <div v-if="store.sectionCount === 0" class="pb-editor-canvas__empty" @click.stop>
      <EmptyState
        title="This layout is empty"
        text="Drag a section from the Insert panel, or use Add. It appears here as a selectable node tree."
      />
      <!-- Root drop zone so a palette section can land in an empty canvas. -->
      <OutlineDropZone :parent-id="null" :index="0" kind="inside" class="pb-outline-dropzone--root-empty" />
    </div>

    <div
      v-else
      class="pb-editor-canvas__tree"
      :class="{ 'is-dragging': store.dragActive }"
      @click.stop
    >
      <CanvasNodeTree :nodes="store.nodeTree" :parent-id="null" />
    </div>

    <CanvasContextMenu />
  </main>
</template>
