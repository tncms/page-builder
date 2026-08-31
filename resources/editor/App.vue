<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { useEditorStore } from './stores/editor'
import EditorTopBar from './components/EditorTopBar.vue'
import EditorLeftPanel from './components/EditorLeftPanel.vue'
import EditorCanvasArea from './components/EditorCanvasArea.vue'
import EditorRightPanel from './components/EditorRightPanel.vue'
import { AssetSourcePicker } from './assets'
import { InsertPanel, InsertSurfaces, useInsertShortcut } from './insert'
import SaveAsDesignDialog from './capture/SaveAsDesignDialog.vue'
import ConflictReconciler from './components/ConflictReconciler.vue'
import PbNotice from '../shared-ui/PbNotice.vue'
import { ResourceBrowser, useResourceBrowserLauncher } from './browser'

const store = useEditorStore()

// PB-TEMPLATE-REGISTRY-2-B — the shared Resource Browser (Patterns/Templates). Both this
// Structure Builder and the Frontend/Live Builder mount the SAME component + store.
const resourceBrowser = useResourceBrowserLauncher()

// Phase 26.38 — global Ctrl/Cmd+I opens the unified Insert launcher (never inserts).
useInsertShortcut()

// PB-PROD-2 — protect unsaved work from a raw browser refresh/close/navigation.
onMounted(() => store.installUnloadGuard())
onUnmounted(() => store.teardownUnloadGuard())
</script>

<template>
  <div class="pb-editor">
    <EditorTopBar />

    <!-- PB-TEMPLATE-REGISTRY-2-B — launch the shared Resource Browser (never mutates). -->
    <button type="button" class="pb-rb-launch" aria-haspopup="dialog" @click="resourceBrowser.open('pattern')">
      Patterns
    </button>

    <!-- A typed version conflict owns its own banner + reconcile action; the generic
         error notice covers every other failure so they never double up. -->
    <ConflictReconciler />
    <PbNotice v-if="store.error && !store.conflict" variant="danger" emphasis="solid" class="pb-editor-error">{{ store.error }}</PbNotice>

    <div class="pb-editor-body">
      <EditorLeftPanel />
      <EditorCanvasArea />
      <EditorRightPanel />
    </div>
    <AssetSourcePicker />
    <InsertPanel />
    <InsertSurfaces />
    <SaveAsDesignDialog />
    <ResourceBrowser />
  </div>
</template>
