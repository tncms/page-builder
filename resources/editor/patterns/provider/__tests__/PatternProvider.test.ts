// Phase 26.15 — LocalPatternProvider delegation + no behavior regression.

import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

import { localPatternProvider, patternProvider, LocalPatternProvider } from '../index'
import { patternLibraryRegistry, createPatternDefinition, type PatternDefinition } from '../../library'
import type { SectionNode } from '../../../types'

function section(): SectionNode {
  return { type: 'section', data: { background: '#fff' }, blocks: [{ type: 'heading', data: {} }] }
}
function def(name: string, category = 'hero'): PatternDefinition {
  return createPatternDefinition({ name, category, sections: [section()] })
}

beforeEach(() => {
  setActivePinia(createPinia())
  patternLibraryRegistry.clear()
})

describe('LocalPatternProvider', () => {
  it('is the exported active provider', () => {
    expect(patternProvider).toBe(localPatternProvider)
    expect(localPatternProvider).toBeInstanceOf(LocalPatternProvider)
  })

  it('register/get/all delegate to the registry', () => {
    const a = patternProvider.register(def('A'))
    const b = patternProvider.register(def('B'))
    expect(patternLibraryRegistry.get(a.id)).toBe(a) // stored in the same registry
    expect(patternProvider.get(a.id)).toBe(a)
    expect(patternProvider.all()).toEqual(patternLibraryRegistry.all())
    expect(patternProvider.all().map((p) => p.id)).toEqual([a.id, b.id]) // order preserved
  })

  it('update delegates and preserves id', () => {
    const a = patternProvider.register(def('A'))
    const updated = patternProvider.update(a.id, { name: 'A2' })
    expect(updated?.id).toBe(a.id)
    expect(patternLibraryRegistry.get(a.id)?.name).toBe('A2')
    expect(patternProvider.update('missing', { name: 'x' })).toBeUndefined()
  })

  it('duplicate delegates (fresh id, independent tree)', () => {
    const a = patternProvider.register(def('A'))
    const copy = patternProvider.duplicate(a.id)!
    expect(copy.id).not.toBe(a.id)
    expect(patternLibraryRegistry.has(copy.id)).toBe(true)
    ;(copy.tree.sections[0].data as Record<string, unknown>).background = '#000'
    expect(patternProvider.get(a.id)?.tree.sections[0].data.background).toBe('#fff') // original intact
  })

  it('remove delegates', () => {
    const a = patternProvider.register(def('A'))
    expect(patternProvider.remove(a.id)).toBe(true)
    expect(patternLibraryRegistry.has(a.id)).toBe(false)
    expect(patternProvider.remove('missing')).toBe(false)
  })
})
