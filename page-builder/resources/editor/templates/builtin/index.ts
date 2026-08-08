// Phase 26.25 — Built-in Template Pack Foundation barrel.
//
// Named, versioned, first-party template packs that register into the Phase 26.24
// `BuiltinTemplateDefinitionSource` (one built-in store — no new resolver, no duplicate storage),
// then resolve and apply through the existing runtime (one command, one undo). Validation-first,
// deterministic, immutable after boot. No marketplace / downloads / licensing / remote loading —
// a safe, marketplace-ready foundation. See docs/builtin-template-packs.md.

export * from './builtinPackTypes'
export { collectTreeIssues, type CompatibilityOptions } from './componentCompatibility'
export { validatePack, type ValidatePackOptions } from './packValidation'
export {
  BuiltinTemplatePackRegistry,
  builtinTemplatePackRegistry,
  type BuiltinDefinitionSink,
  type PackRegistrationResult,
} from './builtinPackRegistry'
export { registerBuiltinPacks, BUILTIN_TEMPLATE_PACKS, type RegisterBuiltinPacksOptions } from './registerBuiltinPacks'
export { companyPack } from './packs/company/companyPack'
