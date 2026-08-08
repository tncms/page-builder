import { describe, it, expect } from 'vitest'
import { buildNodeTree, componentPath, componentAncestors, filterNodeTree } from '../layout'
import type { ComponentNode, LayoutPayload } from '../../types'

// sec1 [ b1(heading), ctr(columns) [ btn(button) ] ], sec2 (empty)
function payload(): LayoutPayload {
  return {
    version: 1,
    sections: [
      {
        id: 'sec1',
        type: 'hero',
        data: {},
        blocks: [
          { id: 'b1', type: 'heading', data: {} },
          { id: 'ctr', type: 'columns', data: { blocks: [{ id: 'btn', type: 'button', data: {} }] } },
        ],
      },
      { id: 'sec2', type: 'cta', data: {}, blocks: [] },
    ],
  } as unknown as LayoutPayload
}

const label = (node: ComponentNode) => node.type

describe('buildNodeTree', () => {
  it('builds a nested section → block → nested block tree', () => {
    const tree = buildNodeTree(payload())
    expect(tree.map((n) => n.id)).toEqual(['sec1', 'sec2'])
    expect(tree[0].children.map((n) => n.id)).toEqual(['b1', 'ctr'])
    expect(tree[0].children[1].children.map((n) => n.id)).toEqual(['btn'])
    expect(tree[0].kind).toBe('section')
    expect(tree[0].children[0].kind).toBe('block')
  })
})

describe('componentPath / componentAncestors', () => {
  it('returns the full trail from root section to the component', () => {
    expect(componentPath(payload(), 'btn').map((p) => p.id)).toEqual(['sec1', 'ctr', 'btn'])
  })

  it('carries type and kind for each item', () => {
    const path = componentPath(payload(), 'btn')
    expect(path[0]).toMatchObject({ id: 'sec1', type: 'hero', kind: 'section' })
    expect(path[2]).toMatchObject({ id: 'btn', type: 'button', kind: 'block' })
  })

  it('returns a single item for a root section', () => {
    expect(componentPath(payload(), 'sec2').map((p) => p.id)).toEqual(['sec2'])
  })

  it('returns just the ancestors (path minus the component)', () => {
    expect(componentAncestors(payload(), 'btn').map((p) => p.id)).toEqual(['sec1', 'ctr'])
  })

  it('is empty for unknown or null ids', () => {
    expect(componentPath(payload(), 'nope')).toEqual([])
    expect(componentPath(payload(), null)).toEqual([])
    expect(componentAncestors(payload(), 'sec2')).toEqual([])
  })
})

describe('filterNodeTree', () => {
  it('returns the tree unchanged for an empty query', () => {
    const tree = buildNodeTree(payload())
    expect(filterNodeTree(tree, '', label)).toBe(tree)
  })

  it('keeps only ancestors of a matching leaf', () => {
    const result = filterNodeTree(buildNodeTree(payload()), 'button', label)
    expect(result.map((n) => n.id)).toEqual(['sec1'])
    expect(result[0].children.map((n) => n.id)).toEqual(['ctr'])
    expect(result[0].children[0].children.map((n) => n.id)).toEqual(['btn'])
  })

  it('keeps the full subtree when a node itself matches', () => {
    const result = filterNodeTree(buildNodeTree(payload()), 'hero', label)
    expect(result.map((n) => n.id)).toEqual(['sec1'])
    expect(result[0].children.map((n) => n.id)).toEqual(['b1', 'ctr'])
  })

  it('matches on id and on label as well as type', () => {
    expect(filterNodeTree(buildNodeTree(payload()), 'sec2', label).map((n) => n.id)).toEqual(['sec2'])
    const byLabel = filterNodeTree(buildNodeTree(payload()), 'Fancy', (n) => (n.id === 'btn' ? 'Fancy Button' : n.type))
    expect(byLabel[0].children[0].children[0].id).toBe('btn')
  })

  it('returns an empty tree when nothing matches', () => {
    expect(filterNodeTree(buildNodeTree(payload()), 'zzz', label)).toEqual([])
  })
})
