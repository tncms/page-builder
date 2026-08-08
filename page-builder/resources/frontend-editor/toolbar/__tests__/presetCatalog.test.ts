// Phase 26.1.1 — presetCatalog: ordering, search, responsive column count.
import { describe, it, expect } from 'vitest'
import {
  dedupeById,
  orderedPresets,
  presetHaystack,
  presetMatchesQuery,
  gridColumnCount,
} from '../presetCatalog'
import type { ColumnLayoutPreset } from '../../../editor/types'

function preset(id: string, extra: Partial<ColumnLayoutPreset> = {}): ColumnLayoutPreset {
  return { id, label: id, widths: ['100'], ...extra }
}

describe('dedupeById', () => {
  it('keeps the first occurrence of each id', () => {
    const out = dedupeById([preset('a'), preset('b'), { ...preset('a'), label: 'dupe' }])
    expect(out.map((p) => p.id)).toEqual(['a', 'b'])
    expect(out[0].label).toBe('a')
  })
})

describe('orderedPresets', () => {
  it('drops duplicate ids', () => {
    const out = orderedPresets([preset('a', { order: 10 }), preset('a', { order: 5 })])
    expect(out.length).toBe(1)
  })


  it('sorts by declared order ascending', () => {
    const out = orderedPresets([
      preset('b', { order: 20 }),
      preset('a', { order: 10 }),
      preset('c', { order: 30 }),
    ])
    expect(out.map((p) => p.id)).toEqual(['a', 'b', 'c'])
  })

  it('keeps input order for presets without order and does not mutate input', () => {
    const input = [preset('x'), preset('y'), preset('z')]
    const out = orderedPresets(input)
    expect(out.map((p) => p.id)).toEqual(['x', 'y', 'z'])
    expect(input.map((p) => p.id)).toEqual(['x', 'y', 'z'])
  })

  it('places ordered presets before unordered ones', () => {
    const out = orderedPresets([preset('u'), preset('o', { order: 5 })])
    expect(out.map((p) => p.id)).toEqual(['o', 'u'])
  })
})

describe('presetHaystack / presetMatchesQuery', () => {
  const p = preset('halves', {
    label: 'Two Columns',
    description: 'Two equal columns',
    category: 'common',
    keywords: ['half', 'equal'],
  })

  it('builds a lowercase haystack from label + description + category + keywords', () => {
    const h = presetHaystack(p)
    expect(h).toContain('two columns')
    expect(h).toContain('equal')
    expect(h).toContain('common')
    expect(h).toContain('half')
  })

  it('matches on any field', () => {
    expect(presetMatchesQuery(p, 'sidebar')).toBe(false)
    expect(presetMatchesQuery(p, 'equal')).toBe(true)
    expect(presetMatchesQuery(p, 'COMMON')).toBe(true)
    expect(presetMatchesQuery(p, 'half')).toBe(true)
  })

  it('empty query matches everything', () => {
    expect(presetMatchesQuery(p, '   ')).toBe(true)
  })
})

describe('gridColumnCount', () => {
  it('caps at 5 columns', () => {
    expect(gridColumnCount(15)).toBe(5)
  })

  it('is bounded by the item count', () => {
    expect(gridColumnCount(3)).toBe(3)
  })

  it('never returns less than 1', () => {
    expect(gridColumnCount(0)).toBe(1)
  })

  it('respects a custom max', () => {
    expect(gridColumnCount(10, 4)).toBe(4)
  })
})
