// Phase 26.26 — Template Package contracts (a TRANSPORT format, not a runtime format).
//
// A package safely transports templates between sites / into starter kits. The runtime keeps using
// `TemplateDefinition`; a package never replaces it — it wraps definitions with a schema, a
// version, and portable metadata for import/export. No licensing / payments / marketplace /
// analytics / ownership fields. Schema keys are invariants (never localized).

import type { TemplateDefinition } from '../contracts'

/** The package schema key (invariant). Bump only for incompatible format changes. */
export const TEMPLATE_PACKAGE_SCHEMA = 'tncms.template-package'
export const SUPPORTED_PACKAGE_SCHEMAS = [TEMPLATE_PACKAGE_SCHEMA] as const

/** The package format version (invariant). */
export const TEMPLATE_PACKAGE_VERSION = '1.0'
export const SUPPORTED_PACKAGE_VERSIONS = [TEMPLATE_PACKAGE_VERSION] as const

/** Portable, minimal package metadata. `id` is an invariant KEY; name/description are display data. */
export interface TemplatePackageMetadata {
  id: string
  name: string
  description?: string
  author?: string
}

/**
 * Optional, ADDITIVE compatibility requirements a package may declare (Phase 26.27). A package
 * without this block is treated as unconstrained (imports as before). `builder` is a semver range;
 * `requiredComponents` / `requiredFeatures` are capability KEYS the target must provide.
 */
export interface PackageCompatibility {
  builder?: string
  requiredComponents?: string[]
  requiredFeatures?: string[]
}

/** A portable package: schema + version + metadata + the transported definitions. */
export interface TemplatePackage {
  schema: string
  version: string
  metadata: TemplatePackageMetadata
  templates: TemplateDefinition[]
  /** Optional additive compatibility declaration (Phase 26.27). */
  compatibility?: PackageCompatibility
}

/** A single import/export problem. */
export interface PackageIssue {
  code: string
  message: string
  templateId?: string
}

/** Thrown when a package fails import validation — before any registration. */
export class TemplatePackageError extends Error {
  readonly issues: PackageIssue[]
  constructor(message: string, issues: PackageIssue[]) {
    super(message)
    this.name = 'TemplatePackageError'
    this.issues = issues
  }
}
