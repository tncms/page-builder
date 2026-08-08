// Phase 16 — Marker scanning helpers for the iframe runtime (pure DOM utils).
//
// The FrontendEditorRenderer wraps every rendered section/block in a
// `.pb-editor-render-marker` element carrying data-pb-component-{id,kind,type}.
// These helpers map a DOM target back to its nearest marker and read its meta,
// so the runtime never guesses ids from anything but the server-emitted markers.

export interface MarkerMeta {
  id: string
  kind: string
  type: string
}

export const MARKER_SELECTOR = '[data-pb-component-id]'

/** The nearest marker element at/above `target`, scoped to `root`, or null. */
export function closestMarker(target: EventTarget | null, root: Element): HTMLElement | null {
  if (!(target instanceof Element)) {
    return null
  }

  const marker = target.closest(MARKER_SELECTOR)
  if (marker instanceof HTMLElement && root.contains(marker)) {
    return marker
  }

  return null
}

/** Read a marker element's component meta, or null when it isn't a marker. */
export function markerMeta(el: HTMLElement | null): MarkerMeta | null {
  if (!el) return null

  const id = el.getAttribute('data-pb-component-id')
  if (!id) return null

  return {
    id,
    kind: el.getAttribute('data-pb-component-kind') ?? '',
    type: el.getAttribute('data-pb-component-type') ?? '',
  }
}

/** Find a marker element by component id within `root`. */
export function findMarker(root: Element, id: string): HTMLElement | null {
  if (!id) return null
  const el = root.querySelector(`[data-pb-component-id="${cssEscape(id)}"]`)
  return el instanceof HTMLElement ? el : null
}

/** Minimal CSS.escape fallback (ids are server-generated, but stay safe). */
function cssEscape(value: string): string {
  const api = (globalThis as { CSS?: { escape?: (v: string) => string } }).CSS
  if (api?.escape) {
    return api.escape(value)
  }
  return value.replace(/["\\\]]/g, '\\$&')
}
