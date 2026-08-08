// Phase 26.36 — Template Library workspace entry (route-scoped admin bundle).
//
// A SEPARATE, small bundle that mounts the Vue workspace into the Filament admin page's
// `#pb-template-workspace` container. It is referenced ONLY from the template-workspace Blade —
// never the editor, the public frontend, or any unrelated page. It reuses the existing Page Builder
// Vite infrastructure (see vite.workspace.config.ts). Read-only browse + Use hand-off +
// import/export: no BuilderState, command, or runtime.

import { registerBuiltinPacks } from './templates/builtin'
import { registerStarterPacks } from './templates/starterPacks'
import { mountTemplateWorkspace } from './templates/workspace'

const MOUNT_SELECTOR = '#pb-template-workspace'

function boot(): void {
  // Built-in templates power the browse list. Non-critical: a bad/duplicate pack must never block
  // the workspace from mounting.
  try {
    registerBuiltinPacks({ freeze: true })
    registerStarterPacks({ freeze: true })
  } catch {
    /* built-in templates simply won't appear */
  }
  mountTemplateWorkspace(MOUNT_SELECTOR)
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot)
} else {
  boot()
}
