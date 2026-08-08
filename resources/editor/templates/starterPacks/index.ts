// Phase 26.28 — Template Starter Packs & Distribution Foundation barrel.
//
// Named, versioned GROUPS of templates (referenced by id) that a site can adopt. Built-in / local
// pack sources, validation-first + identity-strict registry, reusing the Phase 26.27 compatibility
// system. Packs reference templates — they never own or store them (removing a pack never deletes a
// template). This does NOT replace the Template Library, the runtime, the package format, or the
// Picker. No marketplace / purchase / payment / licensing. See docs/template-starter-packs.md.

export * from './starterPackTypes'
export { validateStarterPack, type ValidateStarterPackOptions } from './starterPackValidation'
export {
  TemplateStarterPackRegistry,
  templateStarterPackRegistry,
  type StarterPackRegistrationResult,
  type RegisterStarterPackOptions,
} from './starterPackRegistry'
export {
  registerStarterPacks,
  BUILTIN_STARTER_PACKS,
  type RegisterStarterPacksOptions,
} from './registerStarterPacks'
export { companyStarterPack } from './packs/companyStarterPack'
export { agencyStarterPack } from './packs/agencyStarterPack'
