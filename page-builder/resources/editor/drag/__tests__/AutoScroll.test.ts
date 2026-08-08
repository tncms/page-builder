import { describe, it, expect } from 'vitest'
import { AutoScroll } from '../AutoScroll'

const VP = { top: 0, bottom: 600, left: 0, right: 400 }

describe('AutoScroll.velocityFor', () => {
  const auto = new AutoScroll({ edge: 60, maxSpeed: 18 })

  it('is zero in the middle of the viewport', () => {
    expect(auto.velocityFor(VP, 200, 300)).toEqual({ vx: 0, vy: 0 })
  })

  it('scrolls up near the top edge', () => {
    const v = auto.velocityFor(VP, 200, 5)
    expect(v.vy).toBeLessThan(0)
    expect(v.vx).toBe(0)
  })

  it('scrolls down near the bottom edge', () => {
    const v = auto.velocityFor(VP, 200, 595)
    expect(v.vy).toBeGreaterThan(0)
  })

  it('scrolls left and right near the horizontal edges', () => {
    expect(auto.velocityFor(VP, 5, 300).vx).toBeLessThan(0)
    expect(auto.velocityFor(VP, 395, 300).vx).toBeGreaterThan(0)
  })

  it('ramps faster the closer to the border', () => {
    const near = auto.velocityFor(VP, 200, 1).vy
    const far = auto.velocityFor(VP, 200, 55).vy
    expect(Math.abs(near)).toBeGreaterThan(Math.abs(far))
  })

  it('never exceeds the configured max speed', () => {
    const v = auto.velocityFor(VP, 200, 0)
    expect(Math.abs(v.vy)).toBeLessThanOrEqual(18)
  })
})

describe('AutoScroll loop', () => {
  it('scrolls the viewport while near an edge and stops on stop()', async () => {
    const auto = new AutoScroll({ edge: 60, maxSpeed: 10 })
    const el = document.createElement('div')
    Object.defineProperty(el, 'getBoundingClientRect', { value: () => VP })
    el.scrollTop = 100

    auto.update(el, 200, 595)
    await new Promise((r) => setTimeout(r, 20))
    expect(el.scrollTop).toBeGreaterThan(100)

    auto.stop()
    const settled = el.scrollTop
    await new Promise((r) => setTimeout(r, 20))
    expect(el.scrollTop).toBe(settled)
  })
})
