// HOTFIX — one coordinate contract for all iframe↔shell overlay geometry.
//
// The bug this replaces: every overlay (box-model, inline input, toolbar, drag
// marker) independently read `frameEl.getBoundingClientRect()` and added it to
// the iframe-reported rect, then painted with `position: fixed` and NO clipping.
// Correct in isolation, but the margin box / handles bled under the sidebar and
// nothing kept the offset fresh. This module is the single conversion authority.
//
// THE CONTRACT
// ------------
// Iframe → shell messages ALWAYS carry iframe-viewport rects, taken verbatim from
// `element.getBoundingClientRect()` inside the iframe document. They never bake in
// the iframe element's own screen position, the sidebar width, or any scroll
// (getBoundingClientRect already reflects scroll).
//
// The shell converts EXACTLY ONCE. Overlays that live inside the
// `.pb-fe-overlay-viewport` host (positioned exactly over the iframe's visible
// rectangle) use the iframe-viewport rect as host-LOCAL coordinates — the host
// origin and the iframe origin coincide, so no arithmetic is needed. Overlays
// that must live in shell space (the drag ghost, which tracks the pointer across
// the whole shell) convert through `iframeRectToShellRect` / `iframePointToShellPoint`.

export interface Rect {
  top: number
  left: number
  width: number
  height: number
}

export interface Point {
  x: number
  y: number
}

/**
 * The iframe element's own screen rect — the ONE offset every conversion reads.
 * Null-safe so callers don't special-case an unmounted iframe.
 */
export function frameRect(frameEl: HTMLIFrameElement | null): Rect {
  const r = frameEl?.getBoundingClientRect()
  return { top: r?.top ?? 0, left: r?.left ?? 0, width: r?.width ?? 0, height: r?.height ?? 0 }
}

/**
 * iframe-viewport rect → shell-viewport rect. Adds the frame offset exactly once.
 * The size is carried through unchanged.
 */
export function iframeRectToShellRect(frameEl: HTMLIFrameElement | null, rect: Rect): Rect {
  const f = frameRect(frameEl)
  return {
    top: f.top + rect.top,
    left: f.left + rect.left,
    width: rect.width,
    height: rect.height,
  }
}

/**
 * Clamp a shell-space rect so it stays fully within the frame's visible
 * rectangle — the guard that keeps an overlay (e.g. a floating toolbar) from
 * sliding under the sidebar or off the preview. Degenerate cases (rect larger
 * than the frame) pin to the frame's top-left.
 */
export function clampRectToFrame(frameEl: HTMLIFrameElement | null, rect: Rect): Rect {
  const f = frameRect(frameEl)
  const maxLeft = Math.max(f.left, f.left + f.width - rect.width)
  const maxTop = Math.max(f.top, f.top + f.height - rect.height)
  return {
    top: Math.min(Math.max(rect.top, f.top), maxTop),
    left: Math.min(Math.max(rect.left, f.left), maxLeft),
    width: rect.width,
    height: rect.height,
  }
}

/** shell-viewport point → iframe-viewport point (subtracts the frame offset once). */
export function shellPointToIframePoint(frameEl: HTMLIFrameElement | null, point: Point): Point {
  const f = frameRect(frameEl)
  return { x: point.x - f.left, y: point.y - f.top }
}

/** iframe-viewport point → shell-viewport point (adds the frame offset once). */
export function iframePointToShellPoint(frameEl: HTMLIFrameElement | null, point: Point): Point {
  const f = frameRect(frameEl)
  return { x: f.left + point.x, y: f.top + point.y }
}
