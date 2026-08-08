// Phase 19 — Measure a component's root element into box metrics (iframe side).
// Pure read: getComputedStyle + getBoundingClientRect, no DOM mutation.

import { SIDES, type BoxMetrics, type BoxSides } from './types'

export function parsePx(value: string): number {
  const n = Number.parseFloat(value)
  return Number.isFinite(n) ? n : 0
}

function sides(cs: CSSStyleDeclaration, prop: 'margin' | 'padding' | 'border'): BoxSides {
  const read = (side: string): number =>
    prop === 'border'
      ? parsePx(cs.getPropertyValue(`border-${side}-width`))
      : parsePx(cs.getPropertyValue(`${prop}-${side}`))

  return { top: read('top'), right: read('right'), bottom: read('bottom'), left: read('left') }
}

/** Measure `el` into serialisable box metrics for `componentId`. */
export function measureElement(componentId: string, el: HTMLElement): BoxMetrics {
  const cs = getComputedStyle(el)
  const r = el.getBoundingClientRect()

  return {
    componentId,
    margin: sides(cs, 'margin'),
    padding: sides(cs, 'padding'),
    border: sides(cs, 'border'),
    rect: { top: r.top, left: r.left, width: r.width, height: r.height },
  }
}

export { SIDES }
