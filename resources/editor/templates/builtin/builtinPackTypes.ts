// Phase 26.25 — Built-in template pack contracts.
//
// A "pack" is a named, versioned bundle of built-in `TemplateDefinition`s plus a manifest. Packs
// are the safe, marketplace-ready foundation for shipping first-party templates: they register
// into the Phase 26.24 `BuiltinTemplateDefinitionSource` after validation, and resolve/apply
// through the existing runtime. No marketplace, downloads, licensing, or remote loading.

import type { TemplateDefinition } from '../contracts'

/** Layout schemas a built-in pack may target. */
export const SUPPORTED_TEMPLATE_SCHEMAS = ['pagebuilder/01'] as const
export type SupportedTemplateSchema = (typeof SUPPORTED_TEMPLATE_SCHEMAS)[number]

/** A semver-like version string: MAJOR.MINOR.PATCH. */
export const VERSION_PATTERN = /^\d+\.\d+\.\d+$/

/** Pack metadata. `templates` lists the definition ids the pack ships (invariant KEYS). */
export interface BuiltinTemplatePackManifest {
  id: string
  name: string
  version: string
  description?: string
  /** Layout schema targeted; when omitted, treated as the default supported schema. */
  schema?: string
  /** The template ids this pack contains — must match `definitions` exactly. */
  templates: string[]
}

/** A pack: manifest + its full definitions (registered into the builtin source at load). */
export interface BuiltinTemplatePack {
  manifest: BuiltinTemplatePackManifest
  definitions: TemplateDefinition[]
}

/** A single validation problem, tied to a pack (and optionally a template). */
export interface PackValidationIssue {
  code: string
  message: string
  templateId?: string
}

/** Thrown at LOAD time when a pack fails validation — never deferred to apply. */
export class BuiltinPackError extends Error {
  readonly issues: PackValidationIssue[]
  constructor(message: string, issues: PackValidationIssue[]) {
    super(message)
    this.name = 'BuiltinPackError'
    this.issues = issues
  }
}
