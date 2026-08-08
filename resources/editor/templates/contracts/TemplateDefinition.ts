// Phase 26.16 — Template contract (metadata only, NO runtime).
//
// A Template is a COMPLETE PAGE-LEVEL composition, distinct from a Pattern (a reusable
// layout FRAGMENT). Templates are built ON TOP of Pattern v1 and depend on it — never the
// reverse. This file defines the shape only; there is no resolver, provider storage, or
// UI in this phase.
//
// The layout is described as a REFERENCE, in one of two forms, both of which lean on the
// Pattern subsystem (proving the dependency direction):
//   • `tree`     — an inline page tree, reusing the Phase 26.11 `PatternTree` shape.
//   • `patterns` — a composition of registered pattern ids (resolved via PatternProvider
//                  by a FUTURE runtime; here it is only a reference).

import type { PatternTree } from '../../patterns/library'

/** The two ways a template can describe its page composition. */
export type TemplateLayoutKind = 'tree' | 'patterns'

/** An inline page tree — reuses the Pattern page-tree contract verbatim. */
export interface TemplateTreeLayout {
  kind: 'tree'
  tree: PatternTree
}

/** A composition of registered patterns, referenced by id (resolved by a future runtime). */
export interface TemplatePatternsLayout {
  kind: 'patterns'
  patternIds: string[]
}

/** A template's layout reference. */
export type TemplateLayoutRef = TemplateTreeLayout | TemplatePatternsLayout

/**
 * A registered template. Intentionally small and metadata-only — NO persistence, schema
 * migration, versioning history, permissions, or storage. `version` is a plain string
 * label, not a history system.
 */
export interface TemplateDefinition {
  id: string
  name: string
  description?: string
  category?: string
  /** A metadata-only preview reference (e.g. a thumbnail url); never a live render. */
  preview?: string
  /** How the page composition is described (see `TemplateLayoutRef`). */
  layout: TemplateLayoutRef
  /** Page types this template targets, e.g. `['page', 'landing']`. */
  supportedPageTypes?: string[]
  /** A plain version label (not a history system). */
  version?: string
}

/** Narrow a layout ref to the inline-tree form. */
export function isTemplateTreeLayout(ref: TemplateLayoutRef): ref is TemplateTreeLayout {
  return ref.kind === 'tree'
}

/** Narrow a layout ref to the pattern-composition form. */
export function isTemplatePatternsLayout(ref: TemplateLayoutRef): ref is TemplatePatternsLayout {
  return ref.kind === 'patterns'
}
