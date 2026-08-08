// Phase 26.38 — bind the four Unified Insert providers to the real Builder surfaces.
//
// Phase 26.37 built the router (providers + registry + panel) but left "blocks" and "templates"
// as documented no-ops for the host to wire. This is that host wiring: it composes the default
// handlers (patterns → Pattern Picker, assets → Asset Picker) with real handlers for blocks (focus
// the component palette) and templates (open the Template Browser overlay). Every handler only
// OPENS an existing experience — no resolve, no command, no BuilderState, no runtime.

import {
  defaultInsertOpenHandlers,
  registerBuiltinInsertProviders,
  insertProviderRegistry,
  type InsertOpenHandlers,
} from './builtinInsertProviders'
import { useInsertSurfacesStore } from './insertSurfaces'

/** The default handlers with blocks + templates wired to their host surfaces. */
export function editorInsertOpenHandlers(): InsertOpenHandlers {
  return {
    ...defaultInsertOpenHandlers(),
    blocks: () => useInsertSurfacesStore().focusPalette(),
    templates: () => useInsertSurfacesStore().openTemplates(),
  }
}

/**
 * Register the four built-in providers into the shared registry with the Builder's host handlers.
 * Idempotent: it clears first so a re-boot never trips the registry's duplicate-key guard.
 */
export function registerBuilderInsertProviders(): void {
  insertProviderRegistry.clear()
  registerBuiltinInsertProviders(insertProviderRegistry, editorInsertOpenHandlers())
}
