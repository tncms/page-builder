// Phase 26.21 — Default library composition (the discovery entry point).
//
// A single composite over the three active sources. A FUTURE Template Picker consumes
// `templateLibrary` (never a concrete provider), exactly as the runtime consumes `templateProvider`.
// To add a source later, register its provider here — nothing downstream changes.

import { BuiltinTemplateLibraryProvider } from './BuiltinTemplateLibraryProvider'
import { UserTemplateLibraryProvider } from './UserTemplateLibraryProvider'
import { ImportedTemplateLibraryProvider } from './ImportedTemplateLibraryProvider'
import { CompositeTemplateLibraryProvider } from './CompositeTemplateLibraryProvider'

export const builtinTemplateLibrary = new BuiltinTemplateLibraryProvider()
export const userTemplateLibrary = new UserTemplateLibraryProvider()
export const importedTemplateLibrary = new ImportedTemplateLibraryProvider()

/** The active template library. Discovery reads through here; swap composition, not consumers. */
export const templateLibrary = new CompositeTemplateLibraryProvider([
  builtinTemplateLibrary,
  userTemplateLibrary,
  importedTemplateLibrary,
])
