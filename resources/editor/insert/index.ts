// Phase 26.37 — Unified Insert Experience Foundation barrel.
//
// One Insert entry with four categories (blocks / patterns / templates / assets). Each is an
// InsertProvider that only OPENS its existing experience (Pattern Picker, Template Picker, Asset
// Picker, component palette) — a routing/discovery layer, never insertion logic. It introduces no
// item registry, resolves nothing, dispatches no command, and never touches BuilderState or the
// runtime. No marketplace / premium / generation. See docs/unified-insert-experience.md.

export * from './insertCategory'
export type { InsertProvider } from './InsertProvider'
export { InsertProviderRegistry, InsertRegistryError } from './InsertProviderRegistry'
export {
  makeInsertProvider,
  registerBuiltinInsertProviders,
  defaultInsertOpenHandlers,
  insertProviderRegistry,
  type InsertOpenHandlers,
} from './builtinInsertProviders'
export { useInsertPanelStore } from './insertPanelStore'
export { default as InsertPanel } from './InsertPanel.vue'

// Phase 26.38 — Builder integration: host surfaces, provider wiring, shortcut, overlays.
export { useInsertSurfacesStore } from './insertSurfaces'
export { editorInsertOpenHandlers, registerBuilderInsertProviders } from './editorInsertWiring'
export { useInsertShortcut, handleInsertShortcut } from './useInsertShortcut'
export { default as InsertSurfaces } from './InsertSurfaces.vue'
