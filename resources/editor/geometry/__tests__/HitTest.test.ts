import { describe, it, expect, beforeEach } from 'vitest'
import { GeometryManager } from '../GeometryManager'
import { HitTest } from '../HitTest'
import { marker, mapMeasurer, rect } from './helpers'

// A section (0,0,400,300) containing a block (20,20,200,80). Viewport at origin
// so viewport-space == local for simplicity.
function scene(): { manager: GeometryManager } {
  document.body.innerHTML = ''
  const viewport = document.createElement('div')
  const section = marker('sec', 'section', 'section')
  const block = marker('blk', 'block', 'text')
  section.appendChild(block)
  document.body.append(viewport, section)

  const manager = new GeometryManager(
    mapMeasurer(
      { sec: rect(0, 0, 400, 300), blk: rect(20, 20, 200, 80) },
      rect(0, 0, 800, 600),
    ),
  )
  manager.setViewport(viewport)
  manager.register(section)
  manager.register(block)
  manager.refresh()
  return { manager }
}

describe('HitTest', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('returns the deepest component at a point', () => {
    const { manager } = scene()
    const hit = new HitTest(manager)
    // Inside the block (also inside the section) → deepest wins.
    expect(hit.componentAt(50, 50)).toBe('blk')
    // Inside the section but outside the block.
    expect(hit.componentAt(300, 250)).toBe('sec')
    // Outside everything.
    expect(hit.componentAt(700, 500)).toBeNull()
  })

  it('lists all components at a point, deepest first', () => {
    const { manager } = scene()
    const hit = new HitTest(manager)
    expect(hit.componentsAt(50, 50)).toEqual(['blk', 'sec'])
    expect(hit.componentsAt(300, 250)).toEqual(['sec'])
  })

  it('ignores hidden (zero-size) components', () => {
    document.body.innerHTML = ''
    const viewport = document.createElement('div')
    const a = marker('a')
    document.body.append(viewport, a)
    const manager = new GeometryManager(mapMeasurer({ a: rect(0, 0, 0, 0) }, rect(0, 0, 100, 100)))
    manager.setViewport(viewport)
    manager.register(a)
    manager.refresh()

    expect(new HitTest(manager).componentAt(0, 0)).toBeNull()
  })

  it('looks up a component parent', () => {
    const { manager } = scene()
    const hit = new HitTest(manager)
    expect(hit.parentOf('blk')).toBe('sec')
    expect(hit.parentOf('sec')).toBeNull()
  })
})
