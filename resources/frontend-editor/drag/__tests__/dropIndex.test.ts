import { describe, it, expect } from 'vitest'
import { moveDropIndex, insertDropIndex } from '../dropIndex'
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
        ],
      },
      { id: 's3', type: 'cta', blocks: [] },
    ],
  } as unknown as LayoutPayload
}

describe('moveDropIndex', () => {
  it('compensates a same-parent reorder to a later slot by one', () => {
    // Move s1 after s3: reference s3 (index 2) → 3, compensated to 2 (same parent).
    const index = moveDropIndex(payload(), { parentId: null, index: 0 }, {
      edge: 'after',
      parentId: null,
      referenceId: 's3',
    })
    expect(index).toBe(2)
  })

  it('does not compensate a cross-parent move', () => {
    // Move b1 (in s2) inside empty s1: append at 0.
    const index = moveDropIndex(payload(), { parentId: 's2', index: 0 }, {
      edge: 'inside',
      parentId: 's1',
      referenceId: 's1',
    })
    expect(index).toBe(0)
  })

  it('appends to the end for a cross-parent inside drop', () => {
    const index = moveDropIndex(payload(), { parentId: 's1', index: 0 }, {
      edge: 'inside',
      parentId: 's2',
      referenceId: 's2',
    })
    expect(index).toBe(2)
  })

  it('returns null when the reference is unknown', () => {
    expect(
      moveDropIndex(payload(), { parentId: null, index: 0 }, { edge: 'before', parentId: null, referenceId: 'nope' }),
    ).toBeNull()
  })
})

describe('insertDropIndex', () => {
  it('appends inside a container', () => {
    expect(insertDropIndex(payload(), { edge: 'inside', parentId: 's2', referenceId: 's2' })).toBe(2)
  })

  it('inserts before/after a reference', () => {
    expect(insertDropIndex(payload(), { edge: 'before', parentId: 's2', referenceId: 'b2' })).toBe(1)
    expect(insertDropIndex(payload(), { edge: 'after', parentId: 's2', referenceId: 'b2' })).toBe(2)
  })

  it('appends at the root when there is no reference', () => {
    expect(insertDropIndex(payload(), { edge: 'inside', parentId: null, referenceId: null })).toBe(3)
  })
})
