import { describe, it, expect } from 'vitest'
import { GeometryCache } from '../GeometryCache'
import { rect } from './helpers'

describe('GeometryCache', () => {
  it('stores and returns rects by id', () => {
    const cache = new GeometryCache()
    const r = rect(10, 20, 100, 40)
    cache.set('a', r)
    expect(cache.get('a')).toEqual(r)
    expect(cache.has('a')).toBe(true)
    expect(cache.get('missing')).toBeUndefined()
  })

  it('treats unknown ids as stale', () => {
    const cache = new GeometryCache()
    expect(cache.isStale('a')).toBe(true)
  })

  it('marks entries stale after invalidation', () => {
    const cache = new GeometryCache()
    cache.set('a', rect(0, 0, 10, 10))
    expect(cache.isStale('a')).toBe(false)

    cache.invalidate()
    expect(cache.isStale('a')).toBe(true)

    // Rect stays readable until re-measured, so overlays don't blink to zero.
    expect(cache.get('a')).toBeDefined()
  })

  it('advances the invalidation stamp on each invalidate', () => {
    const cache = new GeometryCache()
    const start = cache.currentStamp()
    cache.invalidate()
    cache.invalidate()
    expect(cache.currentStamp()).toBe(start + 2)
  })

  it('records the stamp a rect was measured against', () => {
    const cache = new GeometryCache()
    cache.set('a', rect(0, 0, 1, 1))
    const s = cache.stampOf('a')
    cache.invalidate()
    cache.set('a', rect(0, 0, 2, 2))
    expect(cache.stampOf('a')).toBe((s ?? 0) + 1)
  })

  it('forgets and clears entries', () => {
    const cache = new GeometryCache()
    cache.set('a', rect(0, 0, 1, 1))
    cache.set('b', rect(0, 0, 1, 1))
    cache.forget('a')
    expect(cache.has('a')).toBe(false)
    expect(cache.has('b')).toBe(true)
    cache.clear()
    expect(cache.has('b')).toBe(false)
  })
})
