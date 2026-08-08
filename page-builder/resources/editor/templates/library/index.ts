// Phase 26.21 — Template Library Foundation barrel.
//
// A DISCOVERY and distribution layer above `TemplateProvider`: browse, search, filter, and
// categorize templates across multiple sources (builtin/user/imported today; marketplace/remote/
// cloud reserved for the future). Metadata-first — no layout trees are loaded while browsing.
// No BuilderState / CommandBus / renderer / Template Runtime changes. See
// docs/template-library-foundation.md.

export * from './templateSource'
export * from './TemplateLibraryItem'
export type { TemplateLibraryProvider } from './TemplateLibraryProvider'
export * from './templateLibraryFilters'
export { sanitizeThumbnail } from './thumbnail'
export { toLibraryItem } from './toLibraryItem'
export { BaseTemplateLibraryProvider } from './BaseTemplateLibraryProvider'
export { StaticTemplateLibraryProvider } from './StaticTemplateLibraryProvider'
export { BuiltinTemplateLibraryProvider } from './BuiltinTemplateLibraryProvider'
export { UserTemplateLibraryProvider } from './UserTemplateLibraryProvider'
export { ImportedTemplateLibraryProvider } from './ImportedTemplateLibraryProvider'
export { CompositeTemplateLibraryProvider } from './CompositeTemplateLibraryProvider'
export {
  templateLibrary,
  builtinTemplateLibrary,
  userTemplateLibrary,
  importedTemplateLibrary,
} from './templateLibrary'
