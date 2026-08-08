// Phase 26.9 — controlled adoption set.
//
// Only a small, deliberate set of editor types gets style binding (token picking +
// source indicator). NOT every field — the brief is explicit about a controlled roll
// out. The adopted types happen to map 1:1 onto token groups (a `color` field ↔ the
// `color` token group), which is why `tokenTypeFor` is a straight passthrough.

import type { SchemaField } from '../schema'

/** Editor types that participate in style binding. */
export const STYLE_BOUND_TYPES: ReadonlySet<string> = new Set(['color', 'spacing', 'typography'])

/** Whether a field should render inside the StyleBoundField wrapper. */
export function isStyleBound(field: SchemaField): boolean {
  return STYLE_BOUND_TYPES.has(field.type)
}

/** The token group a field's picker should offer (null when not adopted). */
export function tokenTypeFor(field: SchemaField): string | null {
  return isStyleBound(field) ? field.type : null
}

/** The "empty" value used when a token binding is removed, per editor shape. */
export function emptyValueFor(field: SchemaField): unknown {
  // color is a string; spacing/typography are objects.
  return field.type === 'color' ? '' : {}
}
