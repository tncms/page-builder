import { describe, it, expect } from 'vitest'
import { positionToolbar } from '../ToolbarPositioner'
import type { ToolbarPositionInput } from '../types'

function input(overrides: Partial<ToolbarPositionInput> = {}): ToolbarPositionInput {
  return {
    component: { top: 200, left: 120, width: 400, height: 300 },
    iframe: { top: 100, left: 60, width: 1000, height: 700 },
    viewport: { width: 1280, height: 900 },
    toolbar: { width: 280, height: 40 },
    gap: 8,
    ...overrides,
  }
}

describe('positionToolbar', () => {
  it('places the toolbar above the component when there is room', () => {
    const r = positionToolbar(input())
    expect(r.placement).toBe('above')
    // screenTop = 100 + 200 = 300; above = 300 - 40 - 8 = 252.
    expect(r.top).toBe(252)
    // left = iframe.left + component.left = 60 + 120 = 180.
    expect(r.left).toBe(180)
    expect(r.clamped).toBe(false)
  })

  it('falls back to inside when there is no room above but the component is tall', () => {
    const r = positionToolbar(
      input({
        component: { top: 0, left: 0, width: 400, height: 300 },
        iframe: { top: 0, left: 0, width: 1000, height: 700 },
      }),
    )
    expect(r.placement).toBe('inside')
    // screenTop = 0; inside = 0 + gap(8).
    expect(r.top).toBe(8)
    expect(r.left).toBe(0)
  })

  it('falls back to below when there is no room above and the component is short', () => {
    const r = positionToolbar(
      input({
        component: { top: 0, left: 0, width: 400, height: 20 },
        iframe: { top: 0, left: 0, width: 1000, height: 700 },
      }),
    )
    expect(r.placement).toBe('below')
    // below = screenTop(0) + height(20) + gap(8) = 28.
    expect(r.top).toBe(28)
  })

  it('clamps horizontally so the toolbar never renders offscreen', () => {
    const r = positionToolbar(
      input({
        component: { top: 200, left: 980, width: 400, height: 300 },
      }),
    )
    // screenLeft = 60 + 980 = 1040; maxLeft = 1280 - 280 = 1000.
    expect(r.left).toBe(1000)
    expect(r.clamped).toBe(true)
  })

  it('clamps vertically so the toolbar never overflows the viewport', () => {
    const r = positionToolbar(
      input({
        component: { top: 0, left: 0, width: 400, height: 20 },
        iframe: { top: 0, left: 0, width: 1000, height: 700 },
        // A very short viewport: even the "below" placement would overflow.
        viewport: { width: 1280, height: 50 },
      }),
    )
    expect(r.placement).toBe('below')
    // below = 0 + 20 + 8 = 28; maxTop = 50 - 40 = 10 → clamped down to 10.
    expect(r.top).toBe(10)
    expect(r.clamped).toBe(true)
  })

  it('clamps left to the iframe edge so it never slides under the sidebar', () => {
    // Component flush to the iframe's left (screenLeft would be the iframe left).
    // A negative-ish component.left must still not push the toolbar left of the
    // iframe (where the sidebar lives).
    const r = positionToolbar(
      input({
        component: { top: 200, left: -40, width: 400, height: 300 },
        iframe: { top: 100, left: 320, width: 900, height: 600 },
      }),
    )
    // screenLeft = 320 + (-40) = 280; floor = iframe.left = 320 → clamped up to 320.
    expect(r.left).toBe(320)
    expect(r.clamped).toBe(true)
  })

  it('recomputes when the geometry (component rect) changes', () => {
    const a = positionToolbar(input({ component: { top: 200, left: 120, width: 400, height: 300 } }))
    const b = positionToolbar(input({ component: { top: 260, left: 120, width: 400, height: 300 } }))
    expect(b.top).toBe(a.top + 60)
  })
})
