// Phase 26.24 — Template Source (definition resolution) barrel.
//
// Binds Library identity → full definition → runtime apply. The Library (26.21) is metadata-only;
// this layer supplies the `TemplateDefinition` a runtime apply needs, keyed by (templateId,
// source). Built-in and imported are in-memory seams; user delegates to the runtime
// `TemplateProvider` (no duplicate storage). Apply still goes through the unchanged runtime — one
// command, one undo. No marketplace / packages / licensing / payment / analytics. See
// docs/template-source-runtime-binding.md.

export type { TemplateDefinitionSource, TemplateDefinitionProvider } from './TemplateDefinitionSource'
export { StaticTemplateDefinitionSource } from './StaticTemplateDefinitionSource'
export { BuiltinTemplateDefinitionSource } from './BuiltinTemplateDefinitionSource'
export { UserTemplateDefinitionSource } from './UserTemplateDefinitionSource'
export { ImportedTemplateDefinitionSource } from './ImportedTemplateDefinitionSource'
export { TemplateSourceResolver } from './TemplateSourceResolver'
export {
  resolveTemplateDefinition,
  applyTemplateFromSource,
  type TemplateSourceApplyDeps,
} from './applyTemplateFromSource'
export {
  templateSourceResolver,
  builtinTemplateDefinitionSource,
  userTemplateDefinitionSource,
  importedTemplateDefinitionSource,
} from './templateSources'
