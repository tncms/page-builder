// Phase 26.26 — export definitions into a portable package.
//
// Wraps definitions in the package envelope (schema + version + metadata) and normalizes each to
// an id-free, JSON-safe form. Portable data (responsive values, tokens, style bindings, state
// styles, asset URLs) is preserved verbatim; only component/runtime ids are stripped. Pure — it
// reads definitions and returns a value; it touches no provider, no runtime, no BuilderState.

import type { TemplateDefinition } from '../contracts'
import {
  TEMPLATE_PACKAGE_SCHEMA,
  TEMPLATE_PACKAGE_VERSION,
  TemplatePackageError,
  type TemplatePackage,
  type TemplatePackageMetadata,
} from './templatePackageTypes'
import { jsonClone, normalizeDefinition } from './packageNormalize'

/** Build a portable package from definitions + metadata. */
export function exportTemplatePackage(
  templates: TemplateDefinition[],
  metadata: TemplatePackageMetadata,
): TemplatePackage {
  return {
    schema: TEMPLATE_PACKAGE_SCHEMA,
    version: TEMPLATE_PACKAGE_VERSION,
    metadata: jsonClone(metadata),
    templates: templates.map(normalizeDefinition),
  }
}

/** Serialize a package to a pretty JSON string (the transport artifact). */
export function serializeTemplatePackage(pkg: TemplatePackage): string {
  return JSON.stringify(pkg, null, 2)
}

/** Parse a package JSON string. Throws `TemplatePackageError` on malformed JSON (not on invalid content). */
export function parseTemplatePackage(json: string): TemplatePackage {
  try {
    return JSON.parse(json) as TemplatePackage
  } catch {
    throw new TemplatePackageError('Package is not valid JSON.', [{ code: 'invalid-json', message: 'Malformed package JSON.' }])
  }
}
