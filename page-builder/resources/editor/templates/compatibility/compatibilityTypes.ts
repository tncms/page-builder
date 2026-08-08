// Phase 26.27 — template versioning & compatibility contracts (foundation).
//
// Version and compatibility are METADATA the import boundary reasons about — they never change how
// a template resolves or applies. Contracts + a pure checker + a migration SEAM. No migrations are
// implemented here, and no marketplace concepts (ownership, pricing, remote fetch) exist.

import type { TemplatePackage } from '../package/templatePackageTypes'

/** Compatibility-schema versions this builder understands (invariant KEYS). */
export const SUPPORTED_TEMPLATE_SCHEMA_VERSIONS = ['1'] as const

/** The current Builder version (the environment templates are checked against). */
export const BUILDER_VERSION = '1.0.0'

/** A minimal version-metadata contract for a template/package (machine keys, never localized). */
export interface TemplateCompatibility {
  schemaVersion: string
  templateVersion: string
  /** Optional builder version/range the template targets. */
  builderVersion?: string
}

/** The environment a package is checked against. Missing capability sets = "cannot verify" (warn). */
export interface BuilderEnvironment {
  builderVersion: string
  /** Package schema keys the builder accepts (defaults to the package module's supported set). */
  supportedPackageSchemas?: readonly string[]
  /** Known component types, when available (enables missing-component detection). */
  components?: ReadonlySet<string>
  /** Known feature keys, when available (enables missing-feature detection). */
  features?: ReadonlySet<string>
}

/** One compatibility problem or note. */
export interface CompatibilityIssue {
  code: string
  message: string
}

/** The result of a compatibility check. `reasons` is empty iff compatible; `warnings` never block. */
export interface CompatibilityResult {
  compatible: boolean
  reasons: CompatibilityIssue[]
  warnings: CompatibilityIssue[]
}

/**
 * Migration SEAM (contract only — NOT implemented in this phase). A future provider upgrades a
 * package from one schema version to another. `migrate` must never mutate its input.
 */
export interface TemplateMigrationProvider {
  canMigrate(fromSchemaVersion: string, toSchemaVersion: string): boolean
  migrate(pkg: TemplatePackage): TemplatePackage
}

/** A no-op migration provider: migrates nothing. The explicit "no migrations yet" seam. */
export const nullMigrationProvider: TemplateMigrationProvider = {
  canMigrate: () => false,
  migrate: (pkg) => {
    throw new Error(`No migration available for package "${pkg?.metadata?.id ?? ''}".`)
  },
}
