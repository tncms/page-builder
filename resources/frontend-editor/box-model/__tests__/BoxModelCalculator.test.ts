import { describe, it, expect } from 'vitest'
import {
  clampNonNegative,
  computeBoxes,
  handleCenter,
  sideDelta,
  snap,
} from '../BoxModelCalculator'
import type { BoxMetrics } from '../types'

function metrics(part: Partial<BoxMetrics> = {}): BoxMetrics {
  return {
    componentId: 'c1',
    margin: { top: 10, right: 20, bottom: 30, left: 40 },
    padding: { top: 5, right: 6, bottom: 7, left: 8 },
    border: { top: 0, right: 0, bottom: 0, left: 0 },
    rect: { top: 100, left: 200, width: 300, height: 150 },
    ...part,
  }
}

describe('computeBoxes', () => {
  it('outsets the margin box and insets padding/content from the border box', () => {
    const b = computeBoxes(metrics(), { top: 0, left: 0 })

    // border box = rect
    expect(b.border).toEqual({ top: 100, left: 200, width: 300, height: 150 })
    // margin box grows outward by each margin
    expect(b.margin).toEqual({ top: 90, left: 160, width: 300 + 40 + 20, height: 150 + 10 + 30 })
    // no border => padding box == border box
    expect(b.padding).toEqual(b.border)
    // content box = border inset by padding
    expect(b.content).toEqual({ top: 105, left: 208, width: 300 - 8 - 6, height: 150 - 5 - 7 })
  })

  it('adds the iframe screen offset', () => {
    const b = computeBoxes(metrics(), { top: 1000, left: 500 })
    expect(b.border.top).toBe(1100)
    expect(b.border.left).toBe(700)
  })
})

describe('handleCenter', () => {
  it('returns the midpoint of each side edge', () => {
    const box = { top: 100, left: 200, width: 300, height: 150 }
    expect(handleCenter(box, 'top')).toEqual({ x: 350, y: 100 })
    expect(handleCenter(box, 'bottom')).toEqual({ x: 350, y: 250 })
    expect(handleCenter(box, 'left')).toEqual({ x: 200, y: 175 })
    expect(handleCenter(box, 'right')).toEqual({ x: 500, y: 175 })
  })
})

describe('sideDelta', () => {
  it('maps outward drag to increasing spacing', () => {
    expect(sideDelta('top', 0, -5)).toBe(5)
    expect(sideDelta('bottom', 0, 5)).toBe(5)
    expect(sideDelta('left', -5, 0)).toBe(5)
    expect(sideDelta('right', 5, 0)).toBe(5)
  })
})

describe('clamp + snap', () => {
  it('clamps negatives to zero', () => {
    expect(clampNonNegative(-3)).toBe(0)
    expect(clampNonNegative(7)).toBe(7)
  })

  it('snaps to a 10px grid', () => {
    expect(snap(13)).toBe(10)
    expect(snap(16)).toBe(20)
  })
})
