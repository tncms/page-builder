import { describe, it, expect } from 'vitest'
import {
  childIds,
  selectionAfterDelete,
  canMoveUp,
  canMoveDown,
  moveIndex,
  siblingInsert,
} from '../ToolbarActions'
import type { LayoutPayload } from '../../../editor/types'

function payload(): LayoutPayload {
  return {
    schema: 'pagebuilder/01',
    sections: [
      { id: 's1', type: 'hero', blocks: [] },
      {
        id: 's2',
        type: 'columns',
        blocks: [
          { id: 'b1', type: 'text', data: {} },
          { id: 'b2', type: 'button', data: {} },
          { id: 'b3', type: 'image', data: {} },
        ],
      },
      { id: 's3', type: 'cta', blocks: [] },
    ],
  } as unknown as LayoutPayload
}

describe('childIds', () => {
  it('returns root section ids for a null parent', () => {
    expect(childIds(payload(), null)).toEqual(['s1', 's2', 's3'])
  })

  it('returns direct child ids of a container', () => {
    expect(childIds(payload(), 's2')).toEqual(['b1', 'b2', 'b3'])
  })
})

describe('selectionAfterDelete', () => {
  it('prefers the next sibling', () => {
    expect(selectionAfterDelete(payload(), 's1')).toBe('s2')
    expect(selectionAfterDelete(payload(), 'b2')).toBe('b3')
  })

  it('falls back to the previous sibling when there is no next', () => {
    expect(selectionAfterDelete(payload(), 's3')).toBe('s2')
    expect(selectionAfterDelete(payload(), 'b3')).toBe('b2')
  })

  it('falls back to the parent for an only child', () => {
    const p = {
      schema: 'pagebuilder/01',
      sections: [{ id: 's1', type: 'columns', blocks: [{ id: 'only', type: 'text', data: {} }] }],
    } as unknown as LayoutPayload
    expect(selectionAfterDelete(p, 'only')).toBe('s1')
  })

  it('clears the selection when the last root section is deleted', () => {
    const p = {
      schema: 'pagebuilder/01',
      sections: [{ id: 'solo', type: 'hero', blocks: [] }],
    } as unknown as LayoutPayload
    expect(selectionAfterDelete(p, 'solo')).toBeNull()
  })
})

describe('move guards + index', () => {
  it('reports move availability at the ends', () => {
    const p = payload()
    expect(canMoveUp(p, 'b1')).toBe(false)
    expect(canMoveDown(p, 'b1')).toBe(true)
    expect(canMoveUp(p, 'b3')).toBe(true)
    expect(canMoveDown(p, 'b3')).toBe(false)
  })

  it('computes the post-removal target index', () => {
    const p = payload()
    expect(moveIndex(p, 'b1', 'down')).toEqual({ parentId: 's2', index: 1 })
    expect(moveIndex(p, 'b3', 'up')).toEqual({ parentId: 's2', index: 1 })
    expect(moveIndex(p, 'b1', 'up')).toBeNull()
    expect(moveIndex(p, 'b3', 'down')).toBeNull()
  })
})

describe('siblingInsert', () => {
  it('targets before/after the reference within the same parent', () => {
    const p = payload()
    expect(siblingInsert(p, 'b2', 'before')).toEqual({ parentId: 's2', index: 1 })
    expect(siblingInsert(p, 'b2', 'after')).toEqual({ parentId: 's2', index: 2 })
    expect(siblingInsert(p, 's1', 'after')).toEqual({ parentId: null, index: 1 })
  })

  it('returns null for an unknown id', () => {
    expect(siblingInsert(payload(), 'nope', 'before')).toBeNull()
  })
})
