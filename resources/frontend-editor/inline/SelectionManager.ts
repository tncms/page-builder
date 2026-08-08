// Phase 18 — Inline target resolution + presentation snapshot (iframe side).
//
// Turns a double-clicked DOM node into a serialisable inline-edit descriptor: the
// component + field it belongs to, the current editable text (with <br> restored
// to newlines so multiline edits round-trip), the element's on-screen rect, and a
// typography snapshot the shell overlay copies so the input looks identical.

import { closestMarker, markerMeta } from '../iframe/markers'
import { locateField, type InlineField } from './TextLocator'

export interface InlineRect {
  top: number
  left: number
  width: number
  height: number
}

export interface InlineTarget {
  componentId: string
  type: string
  field: string
  selector: string
  multiline: boolean
  translatable: boolean
  value: string
  rect: InlineRect
  typography: Record<string, string>
}

// Typography/box properties the overlay input mirrors to match the real text.
const TYPO_PROPS = [
  'font-family',
  'font-size',
  'font-weight',
  'font-style',
  'font-variant',
  'line-height',
  'letter-spacing',
  'text-align',
  'text-transform',
  'color',
  'padding-top',
  'padding-right',
  'padding-bottom',
  'padding-left',
]

/** Read the element's editable plain text, converting <br> back to newlines. */
export function readText(el: HTMLElement): string {
  const clone = el.cloneNode(true) as HTMLElement
  clone.querySelectorAll('br').forEach((br) => br.replaceWith(document.createTextNode('\n')))
  return clone.textContent ?? ''
}

/** Snapshot the computed typography of an element as a camelCase style map. */
export function readTypography(el: HTMLElement): Record<string, string> {
  const cs = getComputedStyle(el)
  const out: Record<string, string> = {}
  for (const prop of TYPO_PROPS) {
    out[toCamel(prop)] = cs.getPropertyValue(prop)
  }
  return out
}

/** Viewport-relative rect of the element (already accounts for iframe scroll). */
export function readRect(el: HTMLElement): InlineRect {
  const r = el.getBoundingClientRect()
  return { top: r.top, left: r.left, width: r.width, height: r.height }
}

/**
 * Resolve the inline target for a clicked node within the canvas, plus the live
 * element (kept iframe-side for rect updates / edit styling). Null when the click
 * isn't on an inline-editable field.
 */
export function resolveInlineTarget(
  target: EventTarget | null,
  canvas: Element,
): { descriptor: InlineTarget; el: HTMLElement; field: InlineField } | null {
  if (!(target instanceof Element)) return null

  const marker = closestMarker(target, canvas)
  const meta = markerMeta(marker)
  if (!marker || !meta) return null

  const hit = locateField(meta.type, marker, target)
  if (!hit) return null

  return {
    el: hit.el,
    field: hit.field,
    descriptor: {
      componentId: meta.id,
      type: meta.type,
      field: hit.field.field,
      selector: hit.field.selector,
      multiline: hit.field.multiline,
      translatable: hit.field.translatable,
      value: readText(hit.el),
      rect: readRect(hit.el),
      typography: readTypography(hit.el),
    },
  }
}

function toCamel(prop: string): string {
  return prop.replace(/-([a-z])/g, (_, c: string) => c.toUpperCase())
}
