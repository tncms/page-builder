// Phase 16/20 — Frontend editor shell entry point. Reads the server boot payload
// from window.TN_PB_FRONTEND_EDITOR_BOOT, wires Pinia + both stores (the reused
// structure-editor store for editing state, the shell store for device/iframe),
// and mounts the full-screen Live Builder shell (Phase 20). Only referenced from
// the frontend-editor Filament page — never on the public frontend.

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import BuilderShell from './components/BuilderShell.vue'
import { useEditorStore } from '../../editor/stores/editor'
import { useShellStore } from './shellStore'
import type { FrontendBootData } from './types'
import '../../shared-ui/index.css'
import '../../editor/editor.css'
import './frontend-editor.css'
import './live-builder.css'
import './components/toolbar/toolbar.css'
import './components/drag/live-drag.css'

declare global {
  interface Window {
    TN_PB_FRONTEND_EDITOR_BOOT?: FrontendBootData
  }
}

function boot(): void {
  const data = window.TN_PB_FRONTEND_EDITOR_BOOT
  const el = document.querySelector<HTMLElement>(data?.mount ?? '#pb-frontend-editor-root')

  if (!data || !el) {
    return
  }

  const app = createApp(BuilderShell)
  app.use(createPinia())

  useEditorStore().bootstrap(data)
  useShellStore().bootstrap(data)

  el.innerHTML = ''
  app.mount(el)
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot)
} else {
  boot()
}
