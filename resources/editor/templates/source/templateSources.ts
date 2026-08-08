// Phase 26.24 — default definition sources + resolver (the discovery-to-runtime binding point).
//
// One resolver over the three active sources. The Apply UX resolves through this; swap the
// composition here to add a future source (marketplace/remote/cloud) with no downstream change —
// exactly like `templateProvider` and `templateLibrary`.

import { BuiltinTemplateDefinitionSource } from './BuiltinTemplateDefinitionSource'
import { UserTemplateDefinitionSource } from './UserTemplateDefinitionSource'
import { ImportedTemplateDefinitionSource } from './ImportedTemplateDefinitionSource'
import { TemplateSourceResolver } from './TemplateSourceResolver'

export const builtinTemplateDefinitionSource = new BuiltinTemplateDefinitionSource()
export const userTemplateDefinitionSource = new UserTemplateDefinitionSource()
export const importedTemplateDefinitionSource = new ImportedTemplateDefinitionSource()

/** The active definition resolver. Apply resolves (templateId, source) → definition through here. */
export const templateSourceResolver = new TemplateSourceResolver([
  builtinTemplateDefinitionSource,
  userTemplateDefinitionSource,
  importedTemplateDefinitionSource,
])
