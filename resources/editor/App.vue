<script setup lang="ts">
import { useEditorStore } from './stores/editor'
import EditorTopBar from './components/EditorTopBar.vue'
import EditorLeftPanel from './components/EditorLeftPanel.vue'
import EditorCanvasArea from './components/EditorCanvasArea.vue'
import EditorRightPanel from './components/EditorRightPanel.vue'
import { AssetSourcePicker } from './assets'
import { InsertPanel, InsertSurfaces, useInsertShortcut } from './insert'
import SaveAsDesignDialog from './capture/SaveAsDesignDialog.vue'
import PbNotice from '../shared-ui/PbNotice.vue'

const store = useEditorStore()

// Phase 26.38 — global Ctrl/Cmd+I opens the unified Insert launcher (never inserts).
useInsertShortcut()
</script>

<template>
  <div class="pb-editor">
    <EditorTopBar />

    <PbNotice v-if="store.error" variant="danger" emphasis="solid" class="pb-editor-error">{{ store.error }}</PbNotice>

    <div class="pb-editor-body">
      <EditorLeftPanel />
      <EditorCanvasArea />
      <EditorRightPanel />
    </div>
    <AssetSourcePicker />
    <InsertPanel />
    <InsertSurfaces />
    <SaveAsDesignDialog />
  </div>
</template>
