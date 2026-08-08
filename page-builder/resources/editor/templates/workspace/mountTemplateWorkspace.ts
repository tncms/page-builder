// Phase 26.35 — mount bootstrap for the admin entry.
//
// Creates a standalone Vue app hosting the Template Library workspace and mounts it into a target
// element (the Filament admin page's blade container). It owns its own Pinia so it can run outside
// the editor bundle. Read-only browse + usage hand-off + import/export — no BuilderState, command,
// or runtime. Returns the app so the caller can unmount; a missing target is a safe no-op.

import { createApp, type App } from 'vue'
import { createPinia } from 'pinia'
import TemplateLibraryWorkspace from './TemplateLibraryWorkspace.vue'

/** Mount the workspace into `target` (an element or selector). Returns the app, or null if absent. */
export function mountTemplateWorkspace(target: Element | string): App | null {
  const el = typeof target === 'string' ? document.querySelector(target) : target
  if (!el) return null
  const app = createApp(TemplateLibraryWorkspace)
  app.use(createPinia())
  app.mount(el)
  return app
}
