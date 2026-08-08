// Phase 26.26 — Template Package (import/export) foundation barrel.
//
// A portable TRANSPORT format for moving templates between sites / into starter kits. Export wraps
// definitions in a schema+version envelope (id-free, JSON-safe, URLs preserved). Import is
// validation-first + atomic and lands templates in the EXISTING Phase 26.24 imported source + Phase
// 26.21 imported library, from which they resolve and apply through the unchanged runtime (one
// command, one undo). No marketplace / storage / payments / downloads / ownership. See
// docs/template-package-foundation.md.

export * from './templatePackageTypes'
export { jsonClone, stripTreeIds, normalizeDefinition } from './packageNormalize'
export { collectAssetBoundaryIssues } from './packageAssetBoundary'
export { validateTemplatePackage, type ValidatePackageOptions } from './importValidation'
export { exportTemplatePackage, serializeTemplatePackage, parseTemplatePackage } from './exportTemplatePackage'
export {
  importTemplatePackage,
  type ImportTemplatePackageOptions,
  type ImportDefinitionSink,
  type ImportResult,
} from './importTemplatePackage'
