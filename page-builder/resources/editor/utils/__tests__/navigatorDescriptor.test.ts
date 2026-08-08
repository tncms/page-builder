import { describe, expect, it } from 'vitest'
import type { ComponentNode } from '../../types'
import {
  canContainChildren,
  describeNode,
  normalizeColumnWidth,
  type NavigatorDescriptorContext,
} from '../navigatorDescriptor'

function node(partial: Partial<ComponentNode> & Pick<ComponentNode, 'id' | 'type' | 'kind'>): ComponentNode {
  return {
    parentId: null,
    depth: 0,
    disabled: false,
    data: {},
    children: [],
    ...partial,
  }
}

function ctx(partial: Partial<NavigatorDescriptorContext> = {}): NavigatorDescriptorContext {
  return {
    selectedId: null,
    inspectorDirty: false,
    lockedIds: new Set<string>(),
    ...partial,
  }
}

describe('normalizeColumnWidth', () => {
  it('maps legacy aliases to percentages', () => {
    expect(normalizeColumnWidth('half')).toBe('50%')
    expect(normalizeColumnWidth('third')).toBe('33.3333%')
    expect(normalizeColumnWidth('66')).toBe('66.6667%')
  })

  it('defaults nullish/empty to auto', () => {
    expect(normalizeColumnWidth(undefined)).toBe('auto')
    expect(normalizeColumnWidth(null)).toBe('auto')
    expect(normalizeColumnWidth('auto')).toBe('auto')
  })

  it('passes through unknown explicit values', () => {
    expect(normalizeColumnWidth('37.5%')).toBe('37.5%')
  })
})

describe('canContainChildren', () => {
  it('is true for sections', () => {
    expect(canContainChildren(node({ id: 's1', type: 'section', kind: 'section' }))).toBe(true)
  })

  it('is true for blocks whose data carries a blocks array (even when empty)', () => {
    expect(canContainChildren(node({ id: 'c1', type: 'container', kind: 'block', data: { blocks: [] } }))).toBe(true)
  })

  it('is false for leaf blocks', () => {
    expect(canContainChildren(node({ id: 'b1', type: 'button', kind: 'block' }))).toBe(false)
  })
})

describe('describeNode', () => {
  it('reports child count from direct children', () => {
    const n = node({
      id: 'c1', type: 'container', kind: 'block', data: { blocks: [] },
      children: [node({ id: 'a', type: 'column', kind: 'block' }), node({ id: 'b', type: 'column', kind: 'block' })],
    })
    expect(describeNode(n, ctx()).childCount).toBe(2)
  })

  it('emits a normalized column width only for columns', () => {
    const col = describeNode(node({ id: 'k', type: 'column', kind: 'block', data: { width: 'half' } }), ctx())
    expect(col.isColumn).toBe(true)
    expect(col.columnWidth).toBe('50%')

    const block = describeNode(node({ id: 'b', type: 'button', kind: 'block' }), ctx())
    expect(block.isColumn).toBe(false)
    expect(block.columnWidth).toBeNull()
  })

  it('defaults a column with no width to auto', () => {
    expect(describeNode(node({ id: 'k', type: 'column', kind: 'block' }), ctx()).columnWidth).toBe('auto')
  })

  it('marks selection from context', () => {
    const n = node({ id: 'x', type: 'button', kind: 'block' })
    expect(describeNode(n, ctx({ selectedId: 'x' })).isSelected).toBe(true)
    expect(describeNode(n, ctx({ selectedId: 'y' })).isSelected).toBe(false)
  })

  it('is dirty only for the selected node while the inspector has draft edits', () => {
    const n = node({ id: 'x', type: 'button', kind: 'block' })
    expect(describeNode(n, ctx({ selectedId: 'x', inspectorDirty: true })).isDirty).toBe(true)
    // Unselected node never dirty, even when the inspector is dirty for another node.
    expect(describeNode(n, ctx({ selectedId: 'other', inspectorDirty: true })).isDirty).toBe(false)
    // Selected but clean inspector → not dirty.
    expect(describeNode(n, ctx({ selectedId: 'x', inspectorDirty: false })).isDirty).toBe(false)
  })

  it('reads lock from the session set, never the payload', () => {
    const n = node({ id: 'x', type: 'button', kind: 'block' })
    expect(describeNode(n, ctx({ lockedIds: new Set(['x']) })).isLocked).toBe(true)
    expect(describeNode(n, ctx({ lockedIds: new Set(['y']) })).isLocked).toBe(false)
  })

  it('mirrors the existing disabled flag as hidden', () => {
    expect(describeNode(node({ id: 'x', type: 'button', kind: 'block', disabled: true }), ctx()).isHidden).toBe(true)
    expect(describeNode(node({ id: 'x', type: 'button', kind: 'block', disabled: false }), ctx()).isHidden).toBe(false)
  })

  it('is deterministic — identical inputs yield an identical descriptor', () => {
    const n = node({ id: 'c', type: 'container', kind: 'block', data: { blocks: [] }, children: [node({ id: 'a', type: 'column', kind: 'block' })] })
    const c = ctx({ selectedId: 'c', inspectorDirty: true, lockedIds: new Set(['c']) })
    expect(describeNode(n, c)).toEqual(describeNode(n, c))
  })
})
