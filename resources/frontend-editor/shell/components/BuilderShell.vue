<script setup lang="ts">
// Phase 20 — Live Builder shell root. A full-screen (100vw × 100vh) 3-column UX:
//   top bar (actions/device) / left sidebar (Elements·Navigator·Options) /
//   main iframe frame / bottom status bar.
// It reuses every existing subsystem: the store, palette, navigator, inspector,
// and the iframe canvas (with its overlay/inline/box-model overlays). No new
// route, no new store, no core changes — this is purely the chrome.
import { onMounted, onUnmounted } from 'vue'
import { useEditorStore } from '../../../editor/stores/editor'
import BuilderTopBar from './BuilderTopBar.vue'
import BuilderSidebar from './BuilderSidebar.vue'
import BuilderStatusBar from './BuilderStatusBar.vue'
import BuilderToast from './BuilderToast.vue'
import FrontendIframeCanvas from '../FrontendIframeCanvas.vue'
import PbNotice from '../../../shared-ui/PbNotice.vue'

const store = useEditorStore()

// Phase 24.1 — Ctrl/Cmd+S in the Live Builder. saveDraft() flushes the inspector
// first (which now Applies any staged edit), so this is "Apply → Save Draft". The
// structure editor has its own Ctrl+S; the two shells are never mounted together.
function onKeydown(e: KeyboardEvent): void {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
    e.preventDefault()
    void store.saveDraft()
  }
}
onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <div class="pb-live-builder-shell">
    <BuilderTopBar />

    <PbNotice v-if="store.error" variant="danger" emphasis="solid" class="pb-live-builder-error">{{ store.error }}</PbNotice>

    <div class="pb-live-builder-body">
      <BuilderSidebar />
      <main class="pb-live-builder-main">
        <div class="pb-live-builder-frame">
          <FrontendIframeCanvas />
        </div>
        <BuilderToast />
      </main>
    </div>

    <BuilderStatusBar />
  </div>
</template>
