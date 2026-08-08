import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { GeometryObserver } from '../GeometryObserver'

// Capturable fakes so we can fire the observer callbacks deterministically
// instead of depending on real layout/mutation timing.
class FakeObserver {
  static instances: FakeObserver[] = []
  observed: unknown[] = []
  disconnected = false
  constructor(public cb: () => void) {
    FakeObserver.instances.push(this)
  }
  observe(target: unknown): void {
    this.observed.push(target)
  }
  disconnect(): void {
    this.disconnected = true
  }
  fire(): void {
    this.cb()
  }
}

describe('GeometryObserver', () => {
  const g = globalThis as Record<string, unknown>
  let originalResize: unknown
  let originalMutation: unknown

  beforeEach(() => {
    FakeObserver.instances = []
    originalResize = g.ResizeObserver
    originalMutation = g.MutationObserver
    g.ResizeObserver = FakeObserver
    g.MutationObserver = FakeObserver
  })

  afterEach(() => {
    g.ResizeObserver = originalResize
    g.MutationObserver = originalMutation
  })

  it('invalidates on resize and mutation of the host', () => {
    const onChange = vi.fn()
    const obs = new GeometryObserver(onChange)
    const host = document.createElement('div')

    obs.observe(host)
    expect(FakeObserver.instances).toHaveLength(2)

    FakeObserver.instances[0].fire() // resize
    FakeObserver.instances[1].fire() // mutation
    expect(onChange).toHaveBeenCalledTimes(2)
  })

  it('observes an additional size target (the viewport)', () => {
    const obs = new GeometryObserver(vi.fn())
    const host = document.createElement('div')
    const viewport = document.createElement('div')

    obs.observe(host)
    obs.observeSize(viewport)

    const resize = FakeObserver.instances[0]
    expect(resize.observed).toContain(host)
    expect(resize.observed).toContain(viewport)
  })

  it('disconnects both observers', () => {
    const obs = new GeometryObserver(vi.fn())
    obs.observe(document.createElement('div'))
    obs.disconnect()
    expect(FakeObserver.instances.every((o) => o.disconnected)).toBe(true)
    expect(obs.observedHost()).toBeNull()
  })

  it('degrades gracefully when observers are unavailable', () => {
    g.ResizeObserver = undefined
    g.MutationObserver = undefined
    const obs = new GeometryObserver(vi.fn())
    expect(() => obs.observe(document.createElement('div'))).not.toThrow()
  })
})
