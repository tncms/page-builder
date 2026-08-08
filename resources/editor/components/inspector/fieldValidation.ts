// Phase 26.4 — Property validation layer.
//
// One pure validator shared by the per-field inline feedback and the Apply gate, so
// both agree. It NEVER mutates a value — it only reports the first problem (or null).
// Because the Inspector edits a staged draft and only commits on Apply, gating Apply
// on this validator is what guarantees "invalid values never corrupt stored data and
// the previous valid state is preserved".
//
// Conservative by design: an empty value is only invalid when the field is required
// (empty optional fields are valid), and only the checks meaningful for the editors
// we actually ship are performed. Unknown rule shapes are ignored, so this is a
// forward-compatible superset of the previous required-only check with zero
// behavioural change for existing components.

import type { SchemaField } from './schema'
import { baseValue } from './responsive/responsiveValue'

/** Whether a value counts as "empty" for validation purposes. */
function isEmpty(value: unknown): boolean {
  return value === undefined || value === null || value === ''
}

/** A minimally-permissive URL check: absolute URLs, roots, anchors, and mailto/tel. */
function looksLikeUrl(value: string): boolean {
  const v = value.trim()
  if (v === '') return true
  if (/^(https?:)?\/\//i.test(v)) return true // http(s) or protocol-relative
  if (/^(mailto:|tel:)/i.test(v)) return true
  if (v.startsWith('/') || v.startsWith('#') || v.startsWith('?')) return true // relative/anchor
  // A bare "example.com/path" is acceptable; reject obvious spaces-only garbage.
  return /^[^\s]+\.[^\s]+/.test(v)
}

/**
 * The string at the heart of a field's value, used for emptiness + URL checks. Most
 * fields ARE their value; the composite editors (Phase 26.5) carry their meaningful
 * string inside an object — a `link`'s `url`, a `media`'s `src` — so required/URL
 * rules apply to that inner string rather than the (never-empty) wrapper object.
 */
function coreValue(field: SchemaField, value: unknown): unknown {
  // Phase 26.6 — a responsive field validates its always-rendered base (desktop)
  // value, so `required` / URL rules behave as if the field were non-responsive.
  const v = field.responsive ? baseValue(value) : value
  if (v && typeof v === 'object' && !Array.isArray(v)) {
    if (field.type === 'link') return (v as { url?: unknown }).url
    if (field.type === 'media') return (v as { src?: unknown }).src
  }
  return v
}

/**
 * Validate `value` against `field`. Returns a human message for the first problem,
 * or null when the value is acceptable. Pure — safe to call on every keystroke.
 */
export function validateField(field: SchemaField, value: unknown): string | null {
  const core = coreValue(field, value)

  if (isEmpty(core)) {
    return field.required ? 'This field is required.' : null
  }

  if ((field.type === 'url' || field.type === 'link') && typeof core === 'string' && !looksLikeUrl(core)) {
    return 'Enter a valid URL.'
  }

  if (field.type === 'number') {
    const n = typeof value === 'number' ? value : Number(value)
    if (Number.isNaN(n)) return 'Enter a number.'
    const editor = (field.editor ?? {}) as { min?: number; max?: number }
    if (typeof editor.min === 'number' && n < editor.min) return `Must be at least ${editor.min}.`
    if (typeof editor.max === 'number' && n > editor.max) return `Must be at most ${editor.max}.`
  }

  return null
}

/** Convenience: is the value valid for this field? */
export function isFieldValid(field: SchemaField, value: unknown): boolean {
  return validateField(field, value) === null
}
