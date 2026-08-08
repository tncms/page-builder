import { describe, it, expect, afterEach, vi } from 'vitest'
import { reactive } from 'vue'
import { getByPath, setByPath, unsetByPath, clonePlain } from '../dataPath'

describe('getByPath', () => {
  const obj = { a: 1, style: { background: '#fff', pad: { top: 4 } } }

  it('reads shallow and nested dot paths', () => {
    expect(getByPath(obj, 'a')).toBe(1)
    expect(getByPath(obj, 'style.background')).toBe('#fff')
    expect(getByPath(obj, 'style.pad.top')).toBe(4)
  })

  it('returns the fallback for missing segments', () => {
    expect(getByPath(obj, 'nope', 'x')).toBe('x')
    expect(getByPath(obj, 'style.missing', 'd')).toBe('d')
    expect(getByPath(obj, 'a.b.c', 'd')).toBe('d')
  })
})

describe('setByPath (immutable)', () => {
  it('sets a nested path without mutating the source', () => {
    const src = { style: { background: '#fff' } }
    const next = setByPath(src, 'style.background', '#000')
    expect(next).toEqual({ style: { background: '#000' } })
    expect(src.style.background).toBe('#fff') // original untouched
    expect(next).not.toBe(src)
    expect(next.style).not.toBe(src.style)
  })

  it('creates intermediate objects for deep paths', () => {
    expect(setByPath({}, 'button.label', 'Go')).toEqual({ button: { label: 'Go' } })
    expect(setByPath({ a: 1 }, 'x.y.z', 5)).toEqual({ a: 1, x: { y: { z: 5 } } })
  })

  it('replaces a non-object intermediate rather than throwing', () => {
    expect(setByPath({ a: 'str' }, 'a.b', 1)).toEqual({ a: { b: 1 } })
  })
})

describe('unsetByPath (immutable)', () => {
  it('removes a nested key without mutating the source', () => {
    const src = { style: { background: '#fff', color: 'red' } }
    const next = unsetByPath(src, 'style.color')
    expect(next).toEqual({ style: { background: '#fff' } })
    expect(src.style.color).toBe('red')
  })

  it('is a no-op for missing paths', () => {
    expect(unsetByPath({ a: 1 }, 'x.y')).toEqual({ a: 1 })
  })
})

describe('clonePlain', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('deep-clones a reactive object into a plain one', () => {
    const r = reactive({ style: { background: '#fff' }, list: [1, 2] })
    const plain = clonePlain(r)
    expect(plain).toEqual({ style: { background: '#fff' }, list: [1, 2] })
    plain.style.background = '#000'
    expect(r.style.background).toBe('#fff') // clone is detached
  })

  it('works when structuredClone is unavailable/throwing (browser reactive bug)', () => {
    vi.stubGlobal('structuredClone', () => {
      throw new Error('could not be cloned')
    })
    const r = reactive({ a: { b: 1 } })
    expect(clonePlain(r)).toEqual({ a: { b: 1 } })
  })
})
