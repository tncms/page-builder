// Phase 21 — Live Component Runtime (iframe side).
//
// Replaces a SINGLE component's marker in the public-page iframe with freshly
// server-rendered HTML, instead of swapping the whole canvas. The HTML comes
// from the authenticated editor `component-preview` endpoint (trusted Blade
// output) — but we still treat it defensively: parsing happens in an inert
// <template>, any <script> is stripped, and the replacement is scoped strictly
// to the canvas. If the target marker isn't found, we replace nothing and
// report failure so the shell can fall back to a full preview refresh.

import { findMarker, MARKER_SELECTOR } from './markers'

/**
 * Parse an HTML fragment into a single root element, in an inert template so no
 * scripts run and no resources load. Returns null when the fragment has no
 * element root. Strips any <script> so untrusted-looking markup can never eval.
 */
function parseComponent(html: string): HTMLElement | null {
  const template = document.createElement('template')
  template.innerHTML = html.trim()

  template.content.querySelectorAll('script').forEach((node) => node.remove())

  const root = template.content.firstElementChild
  return root instanceof HTMLElement ? root : null
}

/**
 * Replace the marker for `componentId` inside `canvas` with `html`.
 *
 * The replacement element must itself be a component marker (the endpoint always
 * wraps its output in `.pb-editor-render-marker`); parsing yields nothing usable
 * otherwise and we bail. Returns true only when a marker was found AND replaced
 * — the whole operation stays within `canvas`, never touching the wider page.
 */
export function replaceComponent(canvas: Element, componentId: string, html: string): boolean {
  const marker = findMarker(canvas, componentId)
  // Never replace outside the canvas: findMarker already scopes to `canvas`, and
  // we re-assert containment in case a caller passes a detached marker.
  if (!marker || !canvas.contains(marker)) {
    return false
  }

  const next = parseComponent(html)
  if (!next || !next.matches(MARKER_SELECTOR)) {
    return false
  }

  marker.replaceWith(next)
  return true
}
