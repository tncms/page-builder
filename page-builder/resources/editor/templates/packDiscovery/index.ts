// Phase 26.29 — Template Library Pack Discovery Foundation barrel.
//
// Projects Phase 26.28 starter packs into discoverable library metadata — a browse/search/filter
// surface for packs, parallel to template discovery. Projection only: it never installs a pack,
// applies templates, resolves definitions, or mutates the pack registry. Existing template
// discovery, the Picker, the runtime, and the Builder are unchanged. No marketplace / purchase /
// payment / licensing / cloud. See docs/template-library-pack-discovery.md.

export * from './packLibraryItem'
export * from './packDiscoveryFilters'
export type { PackLibraryProvider } from './PackLibraryProvider'
export { StarterPackLibraryProvider, packLibrary } from './StarterPackLibraryProvider'
export { usePackDiscoveryStore } from './packDiscoveryStore'
