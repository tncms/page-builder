// Phase 26.1.1 — useLayoutPreferences: favorites + recents persisted to localStorage.
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useLayoutPreferences, LAYOUT_PREFS_KEY } from '../useLayoutPreferences'

beforeEach(() => {
  setActivePinia(createPinia())
  globalThis.localStorage?.clear?.()
})

describe('favorites', () => {
  it('toggles a favorite on and off', () => {
    const prefs = useLayoutPreferences()
    prefs.load()
    expect(prefs.isFavorite('a')).toBe(false)
    prefs.toggleFavorite('a')
    expect(prefs.isFavorite('a')).toBe(true)
    prefs.toggleFavorite('a')
    expect(prefs.isFavorite('a')).toBe(false)
  })

  it('persists favorites to localStorage under the key', () => {
    const prefs = useLayoutPreferences()
    prefs.load()
    prefs.toggleFavorite('halves')
    const raw = globalThis.localStorage.getItem(LAYOUT_PREFS_KEY)
    expect(raw).toBeTruthy()
    expect(JSON.parse(raw as string).favorites).toContain('halves')
  })

  it('hydrates favorites from localStorage on load', () => {
    globalThis.localStorage.setItem(
      LAYOUT_PREFS_KEY,
      JSON.stringify({ favorites: ['thirds'], recent: [] }),
    )
    const prefs = useLayoutPreferences()
    prefs.load()
    expect(prefs.isFavorite('thirds')).toBe(true)
  })
})

describe('recent', () => {
  it('adds to the front, de-duplicates, and caps at 10', () => {
    const prefs = useLayoutPreferences()
    prefs.load()
    for (let i = 0; i < 12; i++) prefs.addRecent(`p${i}`)
    expect(prefs.recent.length).toBe(10)
    expect(prefs.recent[0]).toBe('p11')
    // p0 and p1 fell off the end.
    expect(prefs.recent).not.toContain('p0')
  })

  it('moves a re-applied preset to the front without duplicating', () => {
    const prefs = useLayoutPreferences()
    prefs.load()
    prefs.addRecent('a')
    prefs.addRecent('b')
    prefs.addRecent('a')
    expect(prefs.recent).toEqual(['a', 'b'])
  })

  it('persists recents', () => {
    const prefs = useLayoutPreferences()
    prefs.load()
    prefs.addRecent('x')
    expect(JSON.parse(globalThis.localStorage.getItem(LAYOUT_PREFS_KEY) as string).recent).toEqual(['x'])
  })
})

describe('localStorage unavailable', () => {
  it('degrades to in-memory without throwing', () => {
    const spy = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('denied')
    })
    const prefs = useLayoutPreferences()
    prefs.load()
    expect(() => prefs.toggleFavorite('a')).not.toThrow()
    expect(prefs.isFavorite('a')).toBe(true)
    spy.mockRestore()
  })
})
