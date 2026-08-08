import { describe, it, expect, afterEach } from 'vitest'
import { deepestMarkerAt, markerCandidatesAt, parentMarker, rootMarkerOf, edgeFor } from '../IframeDropGeometry'
import { standardCanvas } from './dom'

afterEach(() => {
  document.body.innerHTML = ''
})

function marker(canvas: HTMLElement, id: string): HTMLElement {
  return canvas.querySelector<HTMLElement>(`[data-pb-component-id="${id}"]`)!
}

describe('deepestMarkerAt', () => {
  it('returns the most deeply nested marker under the pointer', () => {
    const canvas = standardCanvas()
    expect(deepestMarkerAt(canvas, 100, 130)?.getAttribute('data-pb-component-id')).toBe('b1')
    expect(deepestMarkerAt(canvas, 300, 250)?.getAttribute('data-pb-component-id')).toBe('colB')
  })

  it('returns null in the empty gaps of the canvas', () => {
    const canvas = standardCanvas()
    expect(deepestMarkerAt(canvas, 250, 500)).toBeNull()
  })
})

// Phase 26.3.13 — the hit-test ranks the empty column ABOVE its ancestors, so a
// drop over a column never resolves onto the Columns/Section wrapper. This is
// the geometry contract behind "the image must land inside the column".
describe('markerCandidatesAt (Phase 26.3.13)', () => {
  const idOf = (el: HTMLElement) => el.getAttribute('data-pb-component-id')

  it('ranks the empty column winner-first, above its Columns/Section ancestors', () => {
    const canvas = standardCanvas()
    const hits = markerCandidatesAt(canvas, 300, 250) // inside empty colB
    const ids = hits.map((h) => idOf(h.el))
    // colB is the winner; s2 (the Columns section) is a candidate but ranked below.
    expect(ids[0]).toBe('colB')
    expect(ids).toContain('s2')
    expect(ids.indexOf('colB')).toBeLessThan(ids.indexOf('s2'))
    // Depth strictly decreases (deepest first).
    for (let i = 1; i < hits.length; i++) {
      expect(hits[i - 1].depth).toBeGreaterThanOrEqual(hits[i].depth)
    }
  })

  it('agrees with deepestMarkerAt on the winner', () => {
    const canvas = standardCanvas()
    for (const [x, y] of [[100, 130], [300, 250]] as const) {
      expect(idOf(markerCandidatesAt(canvas, x, y)[0].el)).toBe(idOf(deepestMarkerAt(canvas, x, y)!))
    }
  })
})

describe('marker nesting', () => {
  it('resolves parent + root markers', () => {
    const canvas = standardCanvas()
    const b1 = marker(canvas, 'b1')
    expect(parentMarker(b1, canvas)?.getAttribute('data-pb-component-id')).toBe('colA')
    expect(rootMarkerOf(b1, canvas)?.getAttribute('data-pb-component-id')).toBe('s2')
  })
})

describe('edgeFor', () => {
  const container = { top: 100, left: 0, width: 500, height: 200 }
  const leaf = { top: 110, left: 10, width: 200, height: 40 }

  it('uses thirds for containers', () => {
    expect(edgeFor(container, 120, true)).toBe('before')
    expect(edgeFor(container, 200, true)).toBe('inside')
    expect(edgeFor(container, 285, true)).toBe('after')
  })

  it('uses halves for leaf blocks', () => {
    expect(edgeFor(leaf, 120, false)).toBe('before')
    expect(edgeFor(leaf, 145, false)).toBe('after')
  })
})
