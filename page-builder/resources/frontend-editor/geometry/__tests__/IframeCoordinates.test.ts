import { describe, it, expect } from 'vitest'
import {
  frameRect,
  iframeRectToShellRect,
  clampRectToFrame,
  shellPointToIframePoint,
  iframePointToShellPoint,
  type Rect,
} from '../IframeCoordinates'

// A fake iframe whose screen rect we control (e.g. sitting after a 320px sidebar).
function frame(top: number, left: number, width = 900, height = 600): HTMLIFrameElement {
  const el = document.createElement('iframe')
  el.getBoundingClientRect = () =>
    ({ top, left, width, height, right: left + width, bottom: top + height, x: left, y: top, toJSON() {} }) as DOMRect
  return el
}

const rect = (top: number, left: number, width = 200, height = 100): Rect => ({ top, left, width, height })

describe('frameRect', () => {
  it('reads the iframe screen rect, null-safe', () => {
    expect(frameRect(frame(100, 320))).toEqual({ top: 100, left: 320, width: 900, height: 600 })
    expect(frameRect(null)).toEqual({ top: 0, left: 0, width: 0, height: 0 })
  })
})

describe('iframeRectToShellRect', () => {
  it('adds the frame offset exactly once', () => {
    const shell = iframeRectToShellRect(frame(100, 320), rect(40, 30))
    // 320 + 30 = 350, 100 + 40 = 140 — one addition, size unchanged.
    expect(shell).toEqual({ top: 140, left: 350, width: 200, height: 100 })
  })

  it('maps correctly when the iframe sits after a 320px sidebar', () => {
    const shell = iframeRectToShellRect(frame(0, 320), rect(0, 0))
    expect(shell.left).toBe(320)
    expect(shell.top).toBe(0)
  })

  it('is a pure single-hop conversion: applying it twice double-shifts', () => {
    const f = frame(100, 320)
    const once = iframeRectToShellRect(f, rect(40, 30))
    const twice = iframeRectToShellRect(f, once)
    // Proves the helper always adds the offset — callers must convert only once.
    expect(twice.left).toBe(once.left + 320)
    expect(twice.top).toBe(once.top + 100)
  })

  it('host-local coordinates are the iframe rect verbatim (zero-offset frame)', () => {
    // Overlays inside the host treat iframe-viewport rects as host-local; that is
    // exactly a conversion against a frame at the origin.
    const local = iframeRectToShellRect(frame(0, 0), rect(40, 30))
    expect(local).toEqual({ top: 40, left: 30, width: 200, height: 100 })
  })

  it('does not double-count scroll (getBoundingClientRect already reflects it)', () => {
    // A scrolled page still yields ONE addition of the (already scroll-adjusted)
    // frame rect — the helper never reads window.scrollX/Y.
    const shell = iframeRectToShellRect(frame(-50, 320), rect(10, 10))
    expect(shell.top).toBe(-40)
    expect(shell.left).toBe(330)
  })
})

describe('clampRectToFrame', () => {
  it('keeps a rect from sliding under the sidebar (left floor = frame.left)', () => {
    const clamped = clampRectToFrame(frame(100, 320), rect(150, 0, 280, 40))
    expect(clamped.left).toBe(320)
  })

  it('keeps a rect from spilling past the right/bottom edges', () => {
    const f = frame(0, 0, 1000, 700)
    const clamped = clampRectToFrame(f, rect(690, 980, 280, 40))
    expect(clamped.left).toBe(1000 - 280) // 720
    expect(clamped.top).toBe(700 - 40) // 660
  })

  it('leaves an already-contained rect untouched', () => {
    const r = rect(120, 360, 200, 40)
    expect(clampRectToFrame(frame(100, 320, 1000, 700), r)).toEqual(r)
  })
})

describe('point conversions', () => {
  it('round-trip shell ↔ iframe point', () => {
    const f = frame(100, 320)
    const shell = { x: 400, y: 250 }
    const inIframe = shellPointToIframePoint(f, shell)
    expect(inIframe).toEqual({ x: 80, y: 150 })
    expect(iframePointToShellPoint(f, inIframe)).toEqual(shell)
  })
})
