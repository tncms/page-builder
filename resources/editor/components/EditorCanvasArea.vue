<script setup lang="ts">
import { useEditorStore } from '../stores/editor'
import EditorCanvas from './EditorCanvas.vue'
import EditorVisualCanvas from './EditorVisualCanvas.vue'
import EditorBreadcrumb from './navigator/EditorBreadcrumb.vue'

const store = useEditorStore()
</script>

<template>
  <section class="pb-editor-canvas-area">
    <div class="pb-editor-canvas-modes">
      <button
        type="button"
        :class="{ 'is-active': !store.isVisualMode }"
        @click="store.setCanvasMode('tree')"
      >
        Outline
      </button>
      <button
        type="button"
        :class="{ 'is-active': store.isVisualMode }"
        @click="store.setCanvasMode('visual')"
      >
        Visual
      </button>
    </div>

    <EditorBreadcrumb />

    <EditorVisualCanvas v-if="store.isVisualMode" />
    <EditorCanvas v-else />
  </section>
</template>
