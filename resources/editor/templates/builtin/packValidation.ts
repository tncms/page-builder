// Phase 26.25 — built-in pack validation (validation-first, deterministic).
//
// Validates a pack BEFORE any definition is registered, so a bad pack fails at load, never at
// apply. Checks the manifest (id / name / version / schema), the reference integrity between
// `manifest.templates` and `definitions` (exact 1:1, no orphans, no duplicates), and each
// definition's metadata + component compatibility. Pure: returns issues, registers nothing.

import { isTemplateTreeLayout, type TemplateDefinition } from '../contracts'
import {
  SUPPORTED_TEMPLATE_SCHEMAS,
  VERSION_PATTERN,
  type BuiltinTemplatePack,
  type PackValidationIssue,
} from './builtinPackTypes'
import { collectTreeIssues, type CompatibilityOptions } from './componentCompatibility'

export interface ValidatePackOptions extends CompatibilityOptions {
  /** Pack ids already registered — a clash is reported as a duplicate pack. */
  knownPackIds?: ReadonlySet<string>
}

/** Validate a pack. Empty array = valid. */
export function validatePack(pack: BuiltinTemplatePack, options: ValidatePackOptions = {}): PackValidationIssue[] {
  const issues: PackValidationIssue[] = []
  const manifest = pack?.manifest
  const definitions = pack?.definitions ?? []

  if (!manifest || !manifest.id?.trim()) {
    issues.push({ code: 'invalid-manifest', message: 'Pack manifest is missing an id.' })
    return issues // nothing else is meaningful without an id
  }
  if (!manifest.name?.trim()) {
    issues.push({ code: 'invalid-manifest', message: `Pack "${manifest.id}" is missing a name.` })
  }
  if (!VERSION_PATTERN.test(manifest.version ?? '')) {
    issues.push({ code: 'invalid-version', message: `Pack "${manifest.id}" has an invalid version "${manifest.version}".` })
  }
  if (manifest.schema && !(SUPPORTED_TEMPLATE_SCHEMAS as readonly string[]).includes(manifest.schema)) {
    issues.push({ code: 'unsupported-schema', message: `Pack "${manifest.id}" targets unsupported schema "${manifest.schema}".` })
  }
  if (options.knownPackIds?.has(manifest.id)) {
    issues.push({ code: 'duplicate-pack', message: `Pack "${manifest.id}" is already registered.` })
  }

  // Reference integrity: manifest.templates ↔ definitions, exact 1:1.
  const declared = manifest.templates ?? []
  const defIds = definitions.map((d) => d.id)
  const declaredSet = new Set(declared)
  const defSet = new Set(defIds)
  if (declaredSet.size !== declared.length) {
    issues.push({ code: 'duplicate-reference', message: `Pack "${manifest.id}" lists a template id more than once.` })
  }
  if (defSet.size !== defIds.length) {
    issues.push({ code: 'duplicate-definition', message: `Pack "${manifest.id}" ships a duplicate template definition id.` })
  }
  for (const id of declaredSet) {
    if (!defSet.has(id)) issues.push({ code: 'missing-definition', message: `Pack "${manifest.id}" references "${id}" with no definition.`, templateId: id })
  }
  for (const id of defSet) {
    if (!declaredSet.has(id)) issues.push({ code: 'orphan-definition', message: `Pack "${manifest.id}" ships "${id}" not listed in the manifest.`, templateId: id })
  }

  for (const def of definitions) issues.push(...validateDefinition(def, options))

  return issues
}

function validateDefinition(def: TemplateDefinition, options: CompatibilityOptions): PackValidationIssue[] {
  const issues: PackValidationIssue[] = []
  const id = def?.id
  if (!id?.trim()) {
    issues.push({ code: 'invalid-definition', message: 'A template definition is missing an id.' })
    return issues
  }
  if (!def.name?.trim()) {
    issues.push({ code: 'invalid-definition', message: `Template "${id}" is missing a name.`, templateId: id })
  }
  if (!def.layout) {
    issues.push({ code: 'invalid-definition', message: `Template "${id}" has no layout.`, templateId: id })
    return issues
  }
  // Built-in packs ship inline trees; validate their component compatibility.
  if (isTemplateTreeLayout(def.layout)) {
    for (const issue of collectTreeIssues(def.layout.tree, options)) {
      issues.push({ ...issue, templateId: id })
    }
  } else {
    issues.push({ code: 'unsupported-layout', message: `Built-in template "${id}" must ship an inline tree layout.`, templateId: id })
  }
  return issues
}
