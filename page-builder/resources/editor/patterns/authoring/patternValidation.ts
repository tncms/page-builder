// Phase 26.13 — pattern authoring validation (pure).
//
// Two concerns: which selected component may be saved (root-type gate) and whether the
// entered metadata is valid. Both are pure and framework-free so the store, the dialog,
// and tests share one source of truth.

import { PATTERN_LIBRARY_CATEGORIES } from '../library'
import type { ComponentKind } from '../../types'

/** Block types that are layout hosts and may root a pattern. */
const SUPPORTED_ROOT_BLOCK_TYPES: ReadonlySet<string> = new Set(['container', 'column'])

/**
 * Whether a node may be saved as a pattern. A pattern is a reusable LAYOUT fragment, so
 * only sections (any section type) and the layout blocks (container/column) qualify —
 * never a leaf block (text/button/image/…) or a detached node.
 */
export function isSupportedRoot(type: string, kind: ComponentKind): boolean {
  return kind === 'section' || SUPPORTED_ROOT_BLOCK_TYPES.has(type)
}

export interface PatternMetadataInput {
  name: string
  category: string
  description?: string
}

export interface PatternMetadataErrors {
  name?: string
  category?: string
}

/** The maximum pattern name length (keeps cards/registry tidy). */
export const PATTERN_NAME_MAX = 80

/** Validate the entered metadata, returning a (possibly empty) error map. */
export function validatePatternMetadata(input: PatternMetadataInput): PatternMetadataErrors {
  const errors: PatternMetadataErrors = {}

  const name = (input.name ?? '').trim()
  if (!name) errors.name = 'Name is required.'
  else if (name.length > PATTERN_NAME_MAX) errors.name = `Name must be ${PATTERN_NAME_MAX} characters or fewer.`

  if (!(PATTERN_LIBRARY_CATEGORIES as readonly string[]).includes(input.category)) {
    errors.category = 'Choose a category.'
  }

  return errors
}

/** Whether the metadata passes validation. */
export function isValidPatternMetadata(input: PatternMetadataInput): boolean {
  return Object.keys(validatePatternMetadata(input)).length === 0
}
