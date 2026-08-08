import { describe, expect, it } from 'vitest'
import type { ComponentNode } from '../../types'
import { flattenVisible, resolveFocusFallback, siblingPosition, type FlatNode } from '../navigatorFlatten'

function node(id: string, parentId: string | null, children: ComponentNode[] = []): ComponentNode {
  return { id, type: 'x', kind: 'block', parentId, depth: 0, disabled: false, data: {}, children }
}

// section
//   ├ a  (expanded) ── a1, a2
//   └ b  (collapsed) ─ b1
const a1 = node('a1', 'a')
const a2 = node('a2', 'a')
const b1 = node('b1', 'b')
const a = node('a', 's', [a1, a2])
const b = node('b', 's', [b1])
const section = node('s', null, [a, b])
const tree = [section]

describe('flattenVisible', () => {
  it('lists rows in document order with 1-based levels', () => {
    const flat = flattenVisible(tree, () => true)
    expect(flat.map((f) => f.id)).toEqual(['s', 'a', 'a1', 'a2', 'b', 'b1'])
    expect(flat.find((f) => f.id === 's')!.level).toBe(1)
    expect(flat.find((f) => f.id === 'a')!.level).toBe(2)
    expect(flat.find((f) => f.id === 'a1')!.level).toBe(3)
  })

  it('omits children of collapsed nodes', () => {
    // b collapsed → b1 hidden; a expanded → a1/a2 visible.
    const expanded = (n: ComponentNode) => n.id !== 'b'
    const flat = flattenVisible(tree, expanded)
    expect(flat.map((f) => f.id)).toEqual(['s', 'a', 'a1', 'a2', 'b'])
  })

  it('marks hasChildren/expanded correctly', () => {
    const flat = flattenVisible(tree, (n) => n.id !== 'b')
    expect(flat.find((f) => f.id === 'b')).toMatchObject({ hasChildren: true, expanded: false })
    expect(flat.find((f) => f.id === 'a')).toMatchObject({ hasChildren: true, expanded: true })
    expect(flat.find((f) => f.id === 'a1')).toMatchObject({ hasChildren: false, expanded: false })
  })
})

describe('resolveFocusFallback', () => {
  const prev: FlatNode[] = flattenVisible(tree, () => true)

  it('falls to the next visible sibling', () => {
    // a1 deleted → next sibling a2.
    const next = prev.filter((f) => f.id !== 'a1')
    expect(resolveFocusFallback(prev, next, 'a1')).toBe('a2')
  })

  it('falls to the previous sibling when no next sibling remains', () => {
    // a2 deleted (a1 still present) → previous sibling a1.
    const next = prev.filter((f) => f.id !== 'a2')
    expect(resolveFocusFallback(prev, next, 'a2')).toBe('a1')
  })

  it('falls to the parent when the node was an only child', () => {
    // b1 deleted (only child of b) → parent b.
    const next = prev.filter((f) => f.id !== 'b1')
    expect(resolveFocusFallback(prev, next, 'b1')).toBe('b')
  })

  it('falls to the first visible node when parent is gone too', () => {
    // a and its subtree removed → focus a1's slot resolves to first remaining (s).
    const next = prev.filter((f) => !['a', 'a1', 'a2'].includes(f.id))
    expect(resolveFocusFallback(prev, next, 'a1')).toBe('s')
  })

  it('returns null when nothing is left', () => {
    expect(resolveFocusFallback(prev, [], 'a1')).toBeNull()
  })
})

describe('siblingPosition', () => {
  it('locates a nested block among its siblings with its parent', () => {
    expect(siblingPosition(tree, 'a1')).toEqual({ parentId: 'a', index: 0, count: 2 })
    expect(siblingPosition(tree, 'a2')).toEqual({ parentId: 'a', index: 1, count: 2 })
  })

  it('reports a root section with a null parent', () => {
    expect(siblingPosition(tree, 's')).toEqual({ parentId: null, index: 0, count: 1 })
  })

  it('returns null for an unknown id', () => {
    expect(siblingPosition(tree, 'nope')).toBeNull()
  })
})
