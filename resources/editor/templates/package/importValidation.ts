// Phase 26.26 — import validation (validation-first, before any registration).
//
// A package is untrusted external data, so it is validated fully before a single definition is
// registered — a bad package fails at import, never at apply. Checks: schema + version supported,
// metadata present, at least one template, no duplicate template ids, and per template: id / name
// / inline-tree layout, component compatibility (reusing the Phase 26.25 checker), and the asset
// portability boundary (no storage ids). Pure: returns issues, registers nothing.

import { isTemplateTreeLayout, type TemplateDefinition } from '../contracts'
import { collectTreeIssues, type CompatibilityOptions } from '../builtin'
import {
  SUPPORTED_PACKAGE_SCHEMAS,
  SUPPORTED_PACKAGE_VERSIONS,
  type PackageIssue,
  type TemplatePackage,
} from './templatePackageTypes'
import { collectAssetBoundaryIssues } from './packageAssetBoundary'

export type ValidatePackageOptions = CompatibilityOptions

/** Validate an imported package. Empty array = importable. */
export function validateTemplatePackage(pkg: TemplatePackage, options: ValidatePackageOptions = {}): PackageIssue[] {
  const issues: PackageIssue[] = []

  if (!pkg || typeof pkg !== 'object') {
    return [{ code: 'invalid-package', message: 'Package is missing or not an object.' }]
  }
  if (!(SUPPORTED_PACKAGE_SCHEMAS as readonly string[]).includes(pkg.schema)) {
    issues.push({ code: 'unsupported-schema', message: `Unsupported package schema "${pkg.schema}".` })
  }
  if (!(SUPPORTED_PACKAGE_VERSIONS as readonly string[]).includes(pkg.version)) {
    issues.push({ code: 'unsupported-version', message: `Unsupported package version "${pkg.version}".` })
  }
  if (!pkg.metadata?.id?.trim() || !pkg.metadata?.name?.trim()) {
    issues.push({ code: 'invalid-metadata', message: 'Package metadata needs an id and a name.' })
  }

  const templates = pkg.templates ?? []
  if (!Array.isArray(templates) || templates.length === 0) {
    issues.push({ code: 'empty-package', message: 'Package contains no templates.' })
    return issues
  }

  const ids = templates.map((t) => t?.id).filter((id): id is string => !!id)
  if (new Set(ids).size !== ids.length) {
    issues.push({ code: 'duplicate-template', message: 'Package contains duplicate template ids.' })
  }

  for (const def of templates) issues.push(...validateTemplate(def, options))

  return issues
}

function validateTemplate(def: TemplateDefinition, options: CompatibilityOptions): PackageIssue[] {
  const issues: PackageIssue[] = []
  const id = def?.id
  if (!id?.trim()) {
    return [{ code: 'invalid-template', message: 'A template is missing an id.' }]
  }
  if (!def.name?.trim()) {
    issues.push({ code: 'invalid-template', message: `Template "${id}" is missing a name.`, templateId: id })
  }
  if (!def.layout) {
    issues.push({ code: 'invalid-template', message: `Template "${id}" has no layout.`, templateId: id })
    return issues
  }
  if (isTemplateTreeLayout(def.layout)) {
    for (const issue of collectTreeIssues(def.layout.tree, options)) issues.push({ ...issue, templateId: id })
  } else {
    issues.push({ code: 'unsupported-layout', message: `Imported template "${id}" must ship an inline tree layout.`, templateId: id })
  }
  issues.push(...collectAssetBoundaryIssues(def))
  return issues
}
