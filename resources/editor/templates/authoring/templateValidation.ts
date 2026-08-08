// Phase 26.18 — template authoring validation (pure).
//
// Two concerns, shared by the store, the dialog, and tests: which selection may become a
// template (root-type gate) and whether the entered metadata is valid. Framework-free.
//
// A Template is a COMPLETE PAGE-LEVEL composition, so its root rule is STRICTER than a
// Pattern's: only sections and containers may root a captured selection, and the whole page
// may be captured as-is. Columns and leaf blocks are meaningful only as Pattern fragments —
// they are rejected here (see docs/template-authoring.md).

import type { ComponentKind } from '../../types'

/** Block types that are page-level layout hosts and may root a template selection. */
const SUPPORTED_ROOT_BLOCK_TYPES: ReadonlySet<string> = new Set(['container'])

/**
 * Whether a selected node may be captured as a template's root. Sections (any section type)
 * and containers qualify; columns, leaf blocks (text/button/image/…) and detached nodes do
 * not — those are Pattern-level fragments, not page compositions.
 */
export function isSupportedTemplateRoot(type: string, kind: ComponentKind): boolean {
  return kind === 'section' || SUPPORTED_ROOT_BLOCK_TYPES.has(type)
}

/**
 * Suggested category vocabulary — a plain list, not a management system. Aligned with the
 * page-composition domain (page types a template targets). Templates may use any of these;
 * a future picker can group by them.
 */
export const TEMPLATE_LIBRARY_CATEGORIES = ['page', 'landing', 'post', 'archive', 'custom'] as const

export type TemplateLibraryCategory = (typeof TEMPLATE_LIBRARY_CATEGORIES)[number]

export interface TemplateMetadataInput {
  name: string
  category: string
  description?: string
}

export interface TemplateMetadataErrors {
  name?: string
  category?: string
}

/** The maximum template name length (keeps a future picker/list tidy). */
export const TEMPLATE_NAME_MAX = 80

/** Validate the entered metadata, returning a (possibly empty) error map. */
export function validateTemplateMetadata(input: TemplateMetadataInput): TemplateMetadataErrors {
  const errors: TemplateMetadataErrors = {}

  const name = (input.name ?? '').trim()
  if (!name) errors.name = 'Name is required.'
  else if (name.length > TEMPLATE_NAME_MAX) errors.name = `Name must be ${TEMPLATE_NAME_MAX} characters or fewer.`

  if (!(TEMPLATE_LIBRARY_CATEGORIES as readonly string[]).includes(input.category)) {
    errors.category = 'Choose a category.'
  }

  return errors
}

/** Whether the metadata passes validation. */
export function isValidTemplateMetadata(input: TemplateMetadataInput): boolean {
  return Object.keys(validateTemplateMetadata(input)).length === 0
}
