// Phase 26.35 — import a package into the workspace (reuses Phase 26.26 + 26.27).
//
// Parses uploaded JSON, then runs the EXISTING validation-first, atomic import: invalid schema,
// incompatible builder, unsafe asset URLs, and duplicate ids are all rejected there — nothing is
// registered on failure. This wrapper turns the thrown `TemplatePackageError` into a structured
// outcome the UI can show as a success/error dialog. It touches no BuilderState, command, or runtime.

import {
  importTemplatePackage,
  parseTemplatePackage,
  TemplatePackageError,
  type ImportTemplatePackageOptions,
  type PackageIssue,
} from '../package'
import type { CompatibilityIssue } from '../compatibility'

export interface WorkspaceImportOutcome {
  ok: boolean
  packageId?: string
  templateIds?: string[]
  /** Non-blocking compatibility warnings from a successful import (Phase 26.27). */
  warnings?: CompatibilityIssue[]
  /** Present on failure — the reject reason plus the specific issues (invalid/incompatible/unsafe/duplicate). */
  error?: { message: string; issues: PackageIssue[] }
}

/**
 * Parse then import a package. Never throws — a `TemplatePackageError` (bad JSON, invalid schema,
 * incompatible, unsafe assets, or duplicate ids) becomes a structured error outcome. On success the
 * templates land in the imported source/library and become discoverable on the next workspace load.
 */
export function importWorkspacePackage(
  json: string,
  options: ImportTemplatePackageOptions = {},
): WorkspaceImportOutcome {
  try {
    const pkg = parseTemplatePackage(json)
    const result = importTemplatePackage(pkg, options)
    return { ok: true, packageId: result.packageId, templateIds: result.templateIds, warnings: result.warnings }
  } catch (e: unknown) {
    if (e instanceof TemplatePackageError) {
      return { ok: false, error: { message: e.message, issues: e.issues } }
    }
    return { ok: false, error: { message: 'The package could not be imported.', issues: [] } }
  }
}
