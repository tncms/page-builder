import { describe, it, expect } from 'vitest'
import { describeDropIntent, dropRejection, isDropAllowed, type DropGuards } from '../dropIntent'

// Pure Drag & Drop decision tests (Increment 7). No store, no mount: the drop
// contract (normalization) and the single commit-time safety rule (rejection)
// are pinned directly so every drag surface shares one verified authority.

const OK: DropGuards = {
  parentType: () => null,
  isLocked: () => false,
  isSelfOrDescendant: () => false,
}

describe('describeDropIntent', () => {
  it('normalizes a palette (new-component) drop: no sourceId, action from edge', () => {
    const intent = describeDropIntent(
      { id: 'palette:text:1', type: 'text', source: 'palette' },
      { edge: 'inside', targetId: 'col', parentId: 'col' },
      (id) => (id === 'col' ? 'column' : null),
    )
    expect(intent).toEqual({
      mode: 'palette',
      sourceId: null,
      sourceType: 'text',
      targetId: 'col',
      targetType: 'column',
      parentId: 'col',
      action: 'inside',
    })
  })

  it('normalizes a move: keeps sourceId and maps before/after', () => {
    const intent = describeDropIntent(
      { id: 'btn', type: 'button' },
      { edge: 'before', targetId: 'img', parentId: 'col' },
      (id) => (id === 'img' ? 'image' : null),
    )
    expect(intent.mode).toBe('move')
    expect(intent.sourceId).toBe('btn')
    expect(intent.action).toBe('before')
    expect(intent.targetType).toBe('image')
  })

  it('defaults a null edge to "inside"', () => {
    const intent = describeDropIntent({ id: 'x', type: 'text' }, { edge: null, targetId: null, parentId: null }, () => null)
    expect(intent.action).toBe('inside')
  })
})

describe('dropRejection', () => {
  const move = describeDropIntent({ id: 'btn', type: 'button' }, { edge: 'inside', targetId: 'col', parentId: 'col' }, () => 'column')
  const paletteColInCol = describeDropIntent(
    { id: 'p', type: 'column', source: 'palette' },
    { edge: 'inside', targetId: 'col', parentId: 'col' },
    () => 'column',
  )

  it('allows a legal move', () => {
    expect(dropRejection(move, { ...OK, parentType: () => 'column' })).toBeNull()
    expect(isDropAllowed(move, { ...OK, parentType: () => 'column' })).toBe(true)
  })

  it('blocks a locked target container', () => {
    expect(dropRejection(move, { ...OK, isLocked: (id) => id === 'col' })).toBe('The drop target is locked.')
  })

  it('blocks moving a locked node', () => {
    expect(dropRejection(move, { ...OK, isLocked: (id) => id === 'btn' })).toBe('This component is locked.')
  })

  it('blocks moving a node into itself or a descendant', () => {
    expect(dropRejection(move, { ...OK, isSelfOrDescendant: () => true })).toBe('A component cannot be moved into itself.')
  })

  it('blocks a nesting violation (column into column)', () => {
    expect(dropRejection(paletteColInCol, { ...OK, parentType: () => 'column' })).toBe('A column cannot go inside a column.')
  })

  it('a palette drop never triggers the source-lock / self checks', () => {
    // sourceId is null for palette, so isLocked/isSelfOrDescendant on the source
    // are skipped; only target-lock + nesting apply.
    expect(dropRejection(paletteColInCol, { ...OK, isSelfOrDescendant: () => true, parentType: () => 'section' })).toBeNull()
  })
})
