// Phase 26.27 — Template Versioning & Compatibility barrel.
//
// Version + compatibility METADATA the import boundary reasons about — it never changes how a
// template resolves or applies. A pure checker gates import (before registration): compatible →
// accepted, incompatible → rejected with reasons, warnings preserved. A migration SEAM exists as a
// contract only (no migrations implemented). No marketplace. See docs/template-versioning-compatibility.md.

export * from './compatibilityTypes'
export { parseVersion, isExactVersion, isValidRange, satisfiesRange } from './versionRange'
export {
  defaultBuilderEnvironment,
  validateTemplateCompatibility,
  checkPackageCompatibility,
  checkCompatibilityRequirements,
} from './checkCompatibility'
