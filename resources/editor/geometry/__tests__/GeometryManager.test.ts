import { describe, it, expect, beforeEach } from 'vitest'
import { GeometryManager } from '../GeometryManager'
import { marker, mapMeasurer, rect } from './helpers'

function viewportEl(): HTMLElement {
  const el = document.createElement('div')
  el.setAttribute('data-viewport', '')
  return el
}

describe('GeometryManager', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('registers markers and reads their kind/type', () => {
    const m = new GeometryManager()
    const el = marker('a', 'section', 'hero')
    const entry = m.register(el)
    expect(entry?.id).toBe('a')
    expect(entry?.kind).toBe('section')
    expect(entry?.type).toBe('hero')
    expect(m.has('a')).toBe(true)
    expect(m.count()).toBe(1)
  })

  it('ignores elements without a component id', () => {
    const m = new GeometryManager()
    expect(m.register(document.createElement('div'))).toBeNull()
    expect(m.count()).toBe(0)
  })

  it('measures rects local to the viewport origin', () => {
    const viewport = viewportEl()
    const a = marker('a')
    document.body.append(viewport, a)

    const m = new GeometryManager(
      mapMeasurer({ a: rect(140, 220, 300, 80) }, rect(40, 20, 800, 600)),
    )
    m.setViewport(viewport)
    m.register(a)
    m.refresh()

    // local = viewport-space rect minus the viewport's own origin (40,20).
    const r = m.rect('a')
    expect(r).toMatchObject({ left: 100, top: 200, width: 300, height: 80, right: 400, bottom: 280 })
  })

  it('bumps the version on every refresh', () => {
    const m = new GeometryManager(mapMeasurer({}, rect(0, 0, 10, 10)))
    const v = m.currentVersion()
    m.refresh()
    m.refresh()
    expect(m.currentVersion()).toBe(v + 2)
  })

  it('derives parent and depth from DOM ancestry', () => {
    const section = marker('sec', 'section', 'section')
    const block = marker('blk', 'block', 'text')
    section.appendChild(block)
    document.body.appendChild(section)

    const m = new GeometryManager(mapMeasurer({}, rect(0, 0, 10, 10)))
    m.register(section)
    m.register(block)
    m.refresh()

    expect(m.entry('sec')?.parentId).toBeNull()
    expect(m.entry('sec')?.depth).toBe(0)
    expect(m.entry('blk')?.parentId).toBe('sec')
    expect(m.entry('blk')?.depth).toBe(1)
  })

  it('flags zero-size boxes as not visible', () => {
    const a = marker('a')
    document.body.appendChild(a)
    const m = new GeometryManager(mapMeasurer({ a: rect(0, 0, 0, 0) }, rect(0, 0, 100, 100)))
    m.register(a)
    m.refresh()
    expect(m.entry('a')?.visible).toBe(false)
  })

  it('syncs the registry to the markers in a host (add + remove)', () => {
    const host = document.createElement('div')
    host.append(marker('a'), marker('b'))
    document.body.appendChild(host)

    const m = new GeometryManager(mapMeasurer({}, rect(0, 0, 10, 10)))
    m.sync(host)
    expect(m.count()).toBe(2)
    expect(m.has('a')).toBe(true)

    // Marker 'a' removed, 'c' added → registry follows.
    host.innerHTML = ''
    host.append(marker('b'), marker('c'))
    m.sync(host)
    expect(m.count()).toBe(2)
    expect(m.has('a')).toBe(false)
    expect(m.has('c')).toBe(true)
  })

  it('returns null for unknown or null ids', () => {
    const m = new GeometryManager()
    expect(m.rect(null)).toBeNull()
    expect(m.rect('nope')).toBeNull()
  })
})
