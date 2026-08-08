import { describe, it, expect, vi } from 'vitest'
import { appendSelection, guardedNavigate, DISCARD_MESSAGE } from '../navigation'

describe('appendSelection', () => {
  it('appends ?selected when there is no query', () => {
    expect(appendSelection('/edit', 'pb-hero')).toBe('/edit?selected=pb-hero')
  })

  it('appends &selected when a query already exists', () => {
    expect(appendSelection('/edit?x=1', 'pb-hero')).toBe('/edit?x=1&selected=pb-hero')
  })

  it('url-encodes the id', () => {
    expect(appendSelection('/edit', 'pb hero/1')).toBe('/edit?selected=pb%20hero%2F1')
  })

  it('is a no-op without a url or id', () => {
    expect(appendSelection(null, 'x')).toBeNull()
    expect(appendSelection('/edit', null)).toBe('/edit')
  })
})

describe('guardedNavigate', () => {
  it('navigates directly when clean', () => {
    const navigate = vi.fn()
    const confirm = vi.fn(() => true)
    const ok = guardedNavigate('/frontend?selected=pb-1', { dirty: false, confirm, navigate })
    expect(ok).toBe(true)
    expect(confirm).not.toHaveBeenCalled()
    expect(navigate).toHaveBeenCalledWith('/frontend?selected=pb-1')
  })

  it('prompts and navigates when dirty and confirmed', () => {
    const navigate = vi.fn()
    const confirm = vi.fn(() => true)
    const ok = guardedNavigate('/frontend', { dirty: true, confirm, navigate })
    expect(ok).toBe(true)
    expect(confirm).toHaveBeenCalledWith(DISCARD_MESSAGE)
    expect(navigate).toHaveBeenCalledOnce()
  })

  it('cancels navigation when dirty and declined', () => {
    const navigate = vi.fn()
    const confirm = vi.fn(() => false)
    const ok = guardedNavigate('/frontend', { dirty: true, confirm, navigate })
    expect(ok).toBe(false)
    expect(navigate).not.toHaveBeenCalled()
  })

  it('does nothing without a url', () => {
    const navigate = vi.fn()
    expect(guardedNavigate(null, { dirty: false, navigate })).toBe(false)
    expect(navigate).not.toHaveBeenCalled()
  })
})
