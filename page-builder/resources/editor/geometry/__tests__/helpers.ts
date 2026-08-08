// Shared fixtures for geometry tests. Builds real marker elements (happy-dom)
// and a measurer that returns scripted viewport rects, so geometry can be
// exercised without a real layout engine.
import type { CachedRect } from '../GeometryCache'
import type { Measurer } from '../GeometryManager'

export function rect(left: number, top: number, width: number, height: number): CachedRect {
  return {
    x: left,
    y: top,
    left,
    top,
    width,
    height,
    right: left + width,
    bottom: top + height,
  }
}

export function marker(id: string, kind = 'block', type = 'text'): HTMLElement {
  const el = document.createElement('div')
  el.setAttribute('data-pb-component-id', id)
  el.setAttribute('data-pb-component-kind', kind)
  el.setAttribute('data-pb-component-type', type)
  return el
}

/** A measurer backed by an id->rect map, keyed on the marker's component id. */
export function mapMeasurer(rects: Record<string, CachedRect>, viewport: CachedRect): Measurer {
  return (el: HTMLElement) => {
    const id = el.getAttribute('data-pb-component-id')
    if (id && rects[id]) return rects[id]
    return viewport
  }
}
