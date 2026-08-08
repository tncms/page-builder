import { describe, it, expect } from 'vitest'
import { canInsert, insertCandidates, resolveInsertTarget } from '../navigatorInsert'
import type { ComponentNode, EditorMetadata } from '../../types'

// Pure insertion-model tests (Increment 6). No store, no mount: placement math and
// NestingPolicy-filtered candidate resolution are pinned directly so the insertion
// contract is independent of the UI and the store command path.

const metadata = {
  version: '1',
  sections: [{ type: 'hero', label: 'Hero' }, { type: 'columns', label: 'Columns' }],
  blocks: [
    { type: 'container', label: 'Container' },
    { type: 'column', label: 'Column' },
    { type: 'heading', label: 'Heading' },
    { type: 'text', label: 'Text' },
    { type: 'button', label: 'Button' },
  ],
  tokens: {},
} as unknown as EditorMetadata

function leaf(type = 'heading'): ComponentNode {
  return { id: 'n1', type, kind: 'block', parentId: 'sec', depth: 1, disabled: false, data: {}, children: [] }
}
function container(): ComponentNode {
  return { id: 'ctr', type: 'container', kind: 'block', parentId: 'sec', depth: 1, disabled: false, data: { blocks: [] }, children: [] }
}
function column(): ComponentNode {
  return { id: 'col', type: 'column', kind: 'block', parentId: 'ctr', depth: 2, disabled: false, data: { blocks: [] }, children: [] }
}
function section(): ComponentNode {
  return { id: 'sec', type: 'hero', kind: 'section', parentId: null, depth: 0, disabled: false, data: {}, children: [] }
}

const slot = { parentId: 'sec', index: 2 }

describe('resolveInsertTarget', () => {
  it('inside targets the node itself, appended', () => {
    expect(resolveInsertTarget(container(), 'inside', slot, 'section')).toEqual({ parentId: 'ctr', parentType: 'container', index: null })
  })

  it('before/after target the parent slot at index / index + 1', () => {
    expect(resolveInsertTarget(leaf(), 'before', slot, 'hero')).toEqual({ parentId: 'sec', parentType: 'hero', index: 2 })
    expect(resolveInsertTarget(leaf(), 'after', slot, 'hero')).toEqual({ parentId: 'sec', parentType: 'hero', index: 3 })
  })

  it('before/after return null without a known sibling slot', () => {
    expect(resolveInsertTarget(leaf(), 'before', null, 'hero')).toBeNull()
    expect(resolveInsertTarget(leaf(), 'after', null, 'hero')).toBeNull()
  })
})

describe('insertCandidates', () => {
  const types = (parentType: string | null) => insertCandidates(metadata, parentType).map((d) => d.type)

  it('returns [] without metadata', () => {
    expect(insertCandidates(null, 'hero')).toEqual([])
    expect(insertCandidates(undefined, null)).toEqual([])
  })

  it('offers only sections at the root', () => {
    expect(types(null)).toEqual(['hero', 'columns'])
  })

  it('a container accepts only columns; a column rejects column/container', () => {
    expect(types('container')).toEqual(['column'])
    expect(types('column')).toEqual(['heading', 'text', 'button'])
  })

  it('a permissive parent (section) accepts every block', () => {
    expect(types('hero')).toEqual(['container', 'column', 'heading', 'text', 'button'])
  })
})

describe('canInsert', () => {
  it('never offers Add Inside on a leaf (permissive nesting notwithstanding)', () => {
    expect(canInsert(leaf(), 'inside', slot, 'hero', metadata)).toBe(false)
  })

  it('offers Add Inside on a container/column/section with candidates', () => {
    expect(canInsert(container(), 'inside', slot, 'section', metadata)).toBe(true)
    expect(canInsert(column(), 'inside', slot, 'container', metadata)).toBe(true)
    expect(canInsert(section(), 'inside', null, null, metadata)).toBe(true)
  })

  it('offers Add Before/After only with a slot and candidates', () => {
    expect(canInsert(leaf(), 'before', slot, 'hero', metadata)).toBe(true)
    expect(canInsert(leaf(), 'after', slot, 'hero', metadata)).toBe(true)
    expect(canInsert(leaf(), 'before', null, 'hero', metadata)).toBe(false)
  })

  it('is false when nothing may be placed (no metadata)', () => {
    expect(canInsert(container(), 'inside', slot, 'section', null)).toBe(false)
    expect(canInsert(leaf(), 'before', slot, 'hero', null)).toBe(false)
  })
})
