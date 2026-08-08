import { describe, it, expect } from 'vitest'
import {
  DEFAULT_CONSTRAINTS,
  applyDelta,
  equalize,
  normalize,
  round4,
  toWidthStrings,
  type DragOptions,
} from '../geometry'

const sum = (a: number[]): number => round4(a.reduce((x, y) => x + y, 0))

function opts(over: Partial<DragOptions> = {}): DragOptions {
  return {
    minPercent: DEFAULT_CONSTRAINTS.minPercent,
    snapPercent: 0, // default off so tests exercise raw math; snap tested explicitly
    alt: false,
    ...over,
  }
}

describe('normalize', () => {
  it('scales weights to sum exactly 100', () => {
    expect(sum(normalize([250, 250]))).toBe(100)
    expect(normalize([250, 250])).toEqual([50, 50])
  })

  it('turns pixel widths into percentages', () => {
    expect(normalize([120, 360])).toEqual([25, 75])
  })

  it('falls back to an equal split for all-zero input', () => {
    expect(normalize([0, 0, 0])).toEqual(equalize(3))
  })
})

describe('equalize', () => {
  it('splits evenly and sums to exactly 100 (no drift)', () => {
    expect(equalize(2)).toEqual([50, 50])
    const thirds = equalize(3)
    expect(sum(thirds)).toBe(100)
    expect(thirds[1]).toBeCloseTo(33.3333, 4)
  })
})

describe('applyDelta — resize left / right', () => {
  it('drags a divider right: left grows, right shrinks (Part 2)', () => {
    const next = applyDelta([50, 50], 0, 20, opts())
    expect(next).toEqual([70, 30])
    expect(sum(next)).toBe(100)
  })

  it('drags a divider left: left shrinks, right grows', () => {
    const next = applyDelta([50, 50], 0, -20, opts())
    expect(next).toEqual([30, 70])
  })

  it('only affects the adjacent pair, never unrelated columns (Part 11)', () => {
    const next = applyDelta([20, 40, 40], 1, 10, opts())
    expect(next).toEqual([20, 50, 30])
  })
})

describe('applyDelta — minimum width (Part 6)', () => {
  it('never shrinks a column below the minimum', () => {
    const next = applyDelta([50, 50], 0, 45, opts({ minPercent: 10 }))
    expect(next).toEqual([90, 10])
  })

  it('never produces a negative width', () => {
    const next = applyDelta([50, 50], 0, 1000, opts())
    expect(Math.min(...next)).toBeGreaterThanOrEqual(10)
    expect(sum(next)).toBe(100)
  })
})

describe('applyDelta — snap (Part 5)', () => {
  it('snaps the divider to the 5% grid by default', () => {
    // 50 + 3% raw would be 53; snapped to the nearest 5% grid → 55.
    const next = applyDelta([50, 50], 0, 3, opts({ snapPercent: 5 }))
    expect(next).toEqual([55, 45])
  })

  it('Shift disables snapping (snapPercent 0) for fine control', () => {
    const next = applyDelta([50, 50], 0, 3, opts({ snapPercent: 0 }))
    expect(next).toEqual([53, 47])
  })
})

describe('applyDelta — ALT symmetric resize (Part 8)', () => {
  it('mirrors the change across the row centre: 30|40|30 → 25|50|25', () => {
    const next = applyDelta([30, 40, 30], 0, -5, opts({ alt: true }))
    expect(next).toEqual([25, 50, 25])
    expect(sum(next)).toBe(100)
  })

  it('is a plain pair resize when alt is off', () => {
    const next = applyDelta([30, 40, 30], 0, -5, opts({ alt: false }))
    expect(next).toEqual([25, 45, 30])
  })
})

describe('applyDelta — guards', () => {
  it('is a no-op (normalised) for out-of-range dividers', () => {
    expect(applyDelta([50, 50], 5, 10, opts())).toEqual([50, 50])
    expect(applyDelta([100], 0, 10, opts())).toEqual([100])
  })
})

describe('toWidthStrings', () => {
  it('serialises to trimmed tokens the layout stores', () => {
    expect(toWidthStrings([30, 70])).toEqual(['30', '70'])
    // equalize(3) parks the +0.0001 residual on the first column so the row sums 100.
    expect(toWidthStrings(equalize(3))).toEqual(['33.3334', '33.3333', '33.3333'])
  })
})
