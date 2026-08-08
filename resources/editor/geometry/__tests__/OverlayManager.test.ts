import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { GeometryManager } from '../GeometryManager'
import { OverlayManager } from '../OverlayManager'
import { marker, mapMeasurer, rect } from './helpers'

// Controllable rAF so we can assert coalescing deterministically.
const g = globalThis as Record<string, unknown>
let queue: (() => void)[] = []
let originalRaf: unknown
let originalCancel: unknown

function flush(): void {
  const pending = queue
  queue = []
  pending.forEach((cb) => cb())
}

function seededManager(): GeometryManager {
  document.body.innerHTML = ''
  const viewport = document.createElement('div')
  const section = marker('sec', 'section', 'section')
  const block = marker('blk', 'block', 'text')
  document.body.append(viewport, section, block)
  const m = new GeometryManager(
    mapMeasurer(
      { sec: rect(0, 0, 400, 200), blk: rect(0, 300, 400, 100) },
      rect(0, 0, 800, 600),
    ),
  )
  m.setViewport(viewport)
  m.register(section)
  m.register(block)
  m.refresh()
  return m
}

describe('OverlayManager', () => {
  beforeEach(() => {
    queue = []
    originalRaf = g.requestAnimationFrame
    originalCancel = g.cancelAnimationFrame
    g.requestAnimationFrame = (cb: () => void) => {
      queue.push(cb)
      return queue.length
    }
    g.cancelAnimationFrame = () => {}
  })

  afterEach(() => {
    g.requestAnimationFrame = originalRaf
    g.cancelAnimationFrame = originalCancel
  })

  it('coalesces many schedule calls into one refresh per frame', () => {
    const overlay = new OverlayManager(seededManager())
    const listener = vi.fn()
    overlay.setListener(listener)

    overlay.scheduleRefresh()
    overlay.scheduleRefresh()
    overlay.scheduleRefresh()
    expect(queue).toHaveLength(1)

    flush()
    expect(listener).toHaveBeenCalledTimes(1)
    expect(listener).toHaveBeenCalledWith(overlay.geometry.currentVersion())
  })

  it('notifies immediately on refreshNow', () => {
    const overlay = new OverlayManager(seededManager())
    const listener = vi.fn()
    overlay.setListener(listener)
    overlay.refreshNow()
    expect(listener).toHaveBeenCalledTimes(1)
  })

  it('exposes only container components as drop targets', () => {
    const overlay = new OverlayManager(seededManager())
    const targets = overlay.dropTargets()
    expect(targets.map((t) => t.id)).toEqual(['sec'])
    expect(targets[0].rect.width).toBe(400)
  })

  it('reports the nearest edge of the component under a point', () => {
    const overlay = new OverlayManager(seededManager())
    // Section spans y 0..200. Near the top → before, near the bottom → after.
    expect(overlay.nearestEdge(10, 10)?.edge).toBe('before')
    expect(overlay.nearestEdge(10, 190)?.edge).toBe('after')
    expect(overlay.nearestEdge(10, 190)?.id).toBe('sec')
    expect(overlay.nearestEdge(700, 590)).toBeNull()
  })

  it('returns a placeholder insertion point (no drag yet)', () => {
    const overlay = new OverlayManager(seededManager())
    expect(overlay.insertionPoint(10, 10)).toEqual({ parentId: null, index: 0 })
  })
})
