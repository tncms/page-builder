import { describe, it, expect } from 'vitest'
import { resolveOutlineDrop, outlineZoneKey, type OutlineZone } from '../outline'
import type { DragSubject } from '../types'

function subject(over: Partial<DragSubject> & Pick<DragSubject, 'id' | 'kind'>): DragSubject {
  return { type: 'text', label: 'X', icon: null, parentId: null, index: 0, ...over }
}

const neverNested = { isSelfOrDescendant: () => false }

describe('resolveOutlineDrop (Phase 14.4)', () => {
  it('returns null when there is no zone under the pointer', () => {
    expect(resolveOutlineDrop(subject({ id: 'a', kind: 'section' }), null, neverNested)).toBeNull()
  })

  it('accepts a section into a root zone', () => {
    const zone: OutlineZone = { parentId: null, index: 2, kind: 'gap' }
    const r = resolveOutlineDrop(subject({ id: 's1', kind: 'section' }), zone, neverNested)
    expect(r).toMatchObject({ valid: true, parentId: null, index: 2 })
    expect(r?.key).toBe(outlineZoneKey(null, 2, 'gap'))
  })

  it('rejects a section into a non-root (nested) zone', () => {
    const zone: OutlineZone = { parentId: 'sec1', index: 0, kind: 'inside' }
    const r = resolveOutlineDrop(subject({ id: 's1', kind: 'section' }), zone, neverNested)
    expect(r?.valid).toBe(false)
    expect(r?.reason).toMatch(/root/i)
  })

  it('accepts a block into a container zone', () => {
    const zone: OutlineZone = { parentId: 'sec1', index: 1, kind: 'gap' }
    const r = resolveOutlineDrop(subject({ id: 'b1', kind: 'block' }), zone, neverNested)
    expect(r).toMatchObject({ valid: true, parentId: 'sec1', index: 1 })
  })

  it('rejects a block dropped at the root', () => {
    const zone: OutlineZone = { parentId: null, index: 0, kind: 'gap' }
    const r = resolveOutlineDrop(subject({ id: 'b1', kind: 'block' }), zone, neverNested)
    expect(r?.valid).toBe(false)
    expect(r?.reason).toMatch(/section or container/i)
  })

  it('rejects dropping a component into its own subtree', () => {
    const zone: OutlineZone = { parentId: 'child-of-b1', index: 0, kind: 'inside' }
    const opts = { isSelfOrDescendant: (p: string | null, s: string) => p === 'child-of-b1' && s === 'b1' }
    const r = resolveOutlineDrop(subject({ id: 'b1', kind: 'block' }), zone, opts)
    expect(r?.valid).toBe(false)
    expect(r?.reason).toMatch(/own child/i)
  })

  it('ignores the subtree guard for palette subjects (not in the tree yet)', () => {
    const zone: OutlineZone = { parentId: 'sec1', index: 0, kind: 'inside' }
    const opts = { isSelfOrDescendant: () => true } // would reject an existing node
    const r = resolveOutlineDrop(
      subject({ id: 'palette:button:1', kind: 'block', source: 'palette' }),
      zone,
      opts,
    )
    expect(r?.valid).toBe(true)
  })
})
