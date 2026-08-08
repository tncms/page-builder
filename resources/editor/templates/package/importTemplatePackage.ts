// Phase 26.26 — import a package into the Imported source (validation-first, atomic).
//
// The import lands templates in the EXISTING seams — nothing new downstream:
//   • definitions → the Phase 26.24 `ImportedTemplateDefinitionSource` (source = 'imported')
//   • metadata    → the Phase 26.21 imported library (discoverable in the Picker)
// From there they resolve and apply through the unchanged runtime (one command, one undo), exactly
// like built-in templates. Import is validation-first and ATOMIC: it validates the whole package,
// then checks for id collisions, and only then registers — a failure registers nothing.

import { importedTemplateDefinitionSource } from '../source'
import { importedTemplateLibrary, toLibraryItem, TEMPLATE_SOURCE } from '../library'
import type { TemplateDefinition } from '../contracts'
import { validateTemplatePackage, type ValidatePackageOptions } from './importValidation'
import { normalizeDefinition } from './packageNormalize'
import { TemplatePackageError, type TemplatePackage } from './templatePackageTypes'
import { checkPackageCompatibility, type BuilderEnvironment, type CompatibilityIssue } from '../compatibility'

/** The imported definition store the import writes to (satisfied by `ImportedTemplateDefinitionSource`). */
export interface ImportDefinitionSink {
  getDefinition(templateId: string): TemplateDefinition | null
  register(definition: TemplateDefinition): TemplateDefinition
}

export interface ImportTemplatePackageOptions {
  sink?: ImportDefinitionSink
  /** Publish imported templates to the imported library for discovery (default true). */
  publishToLibrary?: boolean
  validate?: ValidatePackageOptions
  /** Builder environment for the Phase 26.27 compatibility gate (defaults to the current builder). */
  environment?: BuilderEnvironment
}

export interface ImportResult {
  ok: boolean
  packageId: string
  templateIds: string[]
  /** Non-blocking compatibility warnings (Phase 26.27). */
  warnings: CompatibilityIssue[]
}

/** Validate then import a package. Throws `TemplatePackageError` on any problem — atomic. */
export function importTemplatePackage(pkg: TemplatePackage, options: ImportTemplatePackageOptions = {}): ImportResult {
  const sink = options.sink ?? importedTemplateDefinitionSource
  const publish = options.publishToLibrary !== false

  const issues = validateTemplatePackage(pkg, options.validate)
  if (issues.length > 0) {
    throw new TemplatePackageError(`Package "${pkg?.metadata?.id ?? ''}" is invalid.`, issues)
  }

  // Phase 26.27 — compatibility gate, BEFORE any registration. Incompatible → reject with reasons;
  // warnings are non-blocking and surfaced on the result. A package with no compatibility block is
  // unconstrained, so existing packages import exactly as before.
  const compatibility = checkPackageCompatibility(pkg, options.environment)
  if (!compatibility.compatible) {
    throw new TemplatePackageError(`Package "${pkg.metadata.id}" is not compatible with this builder.`, compatibility.reasons)
  }

  // Normalize up front, then reject id collisions BEFORE any registration (all-or-nothing).
  const normalized = pkg.templates.map(normalizeDefinition)
  const conflicts = normalized.filter((def) => sink.getDefinition(def.id) !== null)
  if (conflicts.length > 0) {
    throw new TemplatePackageError(
      `Package "${pkg.metadata.id}" has templates that are already imported.`,
      conflicts.map((def) => ({ code: 'duplicate-registration', message: `Template "${def.id}" is already imported.`, templateId: def.id })),
    )
  }

  const templateIds: string[] = []
  for (const def of normalized) {
    sink.register(def)
    if (publish) importedTemplateLibrary.register(toLibraryItem(def, TEMPLATE_SOURCE.imported))
    templateIds.push(def.id)
  }

  return { ok: true, packageId: pkg.metadata.id, templateIds, warnings: compatibility.warnings }
}
