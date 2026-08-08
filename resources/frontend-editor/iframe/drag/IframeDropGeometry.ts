// Phase 22.1 — Cross-iframe drag geometry (iframe side). Pure DOM reads: it
// measures marker rects and walks marker nesting. No layout mutation, no
// querySelector guessing beyond the server-emitted `[data-pb-component-id]`
// markers scoped to the canvas.

import { MARKER_SELECTOR } from '../markers'
import type { DropEdge, IframeRect } from './types'

export function rectOf(el: Element): IframeRect {
  const r = el.getBoundingClientRect()
  return { top: r.top, left: r.left, width: r.width, height: r.height }
}

/** True when (x, y) falls inside `el`'s current rect (iframe-viewport coords). */
function contains(el: Element, x: number, y: number): boolean {
  const r = el.getBoundingClientRect()
  return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom
}

/** Depth of marker nesting for `el` within `canvas` (root marker = 0). */
function markerDepth(el: HTMLElement, canvas: Element): number {
  let depth = 0
  let parent = el.parentElement?.closest<HTMLElement>(MARKER_SELECTOR) ?? null
  while (parent && canvas.contains(parent)) {
    depth += 1
    parent = parent.parentElement?.closest<HTMLElement>(MARKER_SELECTOR) ?? null
  }
  return depth
}

/** A marker under the pointer, with the ranking keys used to choose the winner. */
export interface MarkerCandidate {
  el: HTMLElement
  depth: number
  area: number
}

/**
 * Every marker whose rect contains the pointer, ranked winner-first: deepest
 * nesting, then smallest area. Order-independent of the z-index of decorative
 * overlays (pure geometry over marker rects, never elementFromPoint), so an
 * overlay with a higher z-index or `pointer-events:none` cannot steal the hit.
 * Exposed so the drop-debug trace can show WHY a target won (Phase 26.3.13).
 */
export function markerCandidatesAt(canvas: Element, x: number, y: number): MarkerCandidate[] {
  const markers = Array.from(canvas.querySelectorAll<HTMLElement>(MARKER_SELECTOR))
  const hits: MarkerCandidate[] = []
  for (const marker of markers) {
    if (!contains(marker, x, y)) continue
    const r = marker.getBoundingClientRect()
    hits.push({ el: marker, depth: markerDepth(marker, canvas), area: r.width * r.height })
  }
  // Stable sort: deepest first, then smallest area; DOM order breaks exact ties.
  hits.sort((a, b) => b.depth - a.depth || a.area - b.area)
  return hits
}

/**
 * The deepest marker whose rect contains the pointer (the winner of
 * {@link markerCandidatesAt}), or null in the empty gaps.
 */
export function deepestMarkerAt(canvas: Element, x: number, y: number): HTMLElement | null {
  return markerCandidatesAt(canvas, x, y)[0]?.el ?? null
}

/** The nearest ancestor marker of `el` within `canvas`, or null (root). */
export function parentMarker(el: HTMLElement, canvas: Element): HTMLElement | null {
  const parent = el.parentElement?.closest<HTMLElement>(MARKER_SELECTOR) ?? null
  return parent && canvas.contains(parent) ? parent : null
}

/** The top-most (root) marker ancestor of `el`, or `el` itself when at root. */
export function rootMarkerOf(el: HTMLElement, canvas: Element): HTMLElement | null {
  let current: HTMLElement | null = el
  let parent = parentMarker(el, canvas)
  while (parent) {
    current = parent
    parent = parentMarker(parent, canvas)
  }
  return current
}

/**
 * Which edge of `rect` the pointer at `y` (iframe-viewport clientY) is nearest.
 * Containers use a thirds split (before | inside | after) so the middle drops
 * *into* them; leaf blocks / sections use a simple half split.
 */
export function edgeFor(rect: IframeRect, y: number, isContainer: boolean): DropEdge {
  const bottom = rect.top + rect.height
  if (isContainer) {
    const third = rect.height / 3
    if (y < rect.top + third) return 'before'
    if (y > bottom - third) return 'after'
    return 'inside'
  }
  return y < rect.top + rect.height / 2 ? 'before' : 'after'
}
