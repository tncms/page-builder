import { describe, it, expect } from 'vitest'
import { DropManager } from '../DropManager'
import { scene, rect, subject, type MarkerSpec } from './helpers'

// A two-section page; sec1 holds two leaf blocks and a container with a child.
const TREE: MarkerSpec[] = [
  {
    id: 'sec1',
    kind: 'section',
    type: 'section',
    rect: rect(0, 0, 400, 300),
    children: [
      { id: 'blk1', kind: 'block', rect: rect(10, 10, 380, 60) },
      { id: 'blk2', kind: 'block', rect: rect(10, 80, 380, 60) },
      {
        id: 'ctr',
        kind: 'container',
        rect: rect(10, 150, 380, 120),
        children: [{ id: 'child', kind: 'block', rect: rect(20, 180, 360, 50) }],
      },
    ],
  },
  { id: 'sec2', kind: 'section', type: 'section', rect: rect(0, 320, 400, 200) },
]

function manager() {
  const s = scene(TREE)
  return new DropManager(s.geometry, s.hitTest)
}

describe('DropManager — block drops', () => {
  it('drops before a leaf block from its top half', () => {
    const res = manager().resolve(subject({ id: 'blk1', kind: 'block', parentId: 'sec1', index: 0 }), 200, 90)
    expect(res).toMatchObject({ valid: true, edge: 'before', targetId: 'blk2', referenceId: 'blk2', parentId: 'sec1' })
  })

  it('drops after a leaf block from its bottom half', () => {
    const res = manager().resolve(subject({ id: 'blk1', kind: 'block', parentId: 'sec1', index: 0 }), 200, 135)
    expect(res).toMatchObject({ valid: true, edge: 'after', targetId: 'blk2', parentId: 'sec1' })
  })

  it('drops inside a container from its middle band', () => {
    // x=15 is inside the container's padding but outside its child (x >= 20).
    const res = manager().resolve(subject({ id: 'blk1', kind: 'block', parentId: 'sec1', index: 0 }), 15, 210)
    expect(res).toMatchObject({ valid: true, edge: 'inside', targetId: 'ctr', parentId: 'ctr' })
  })

  it('coerces before/after a root section into an inside drop (a block cannot be at the root)', () => {
    // Top third of sec2 would read "before", but a block cannot sit at the root.
    const res = manager().resolve(subject({ id: 'blk1', kind: 'block', parentId: 'sec1', index: 0 }), 200, 330)
    expect(res).toMatchObject({ valid: true, edge: 'inside', parentId: 'sec2' })
  })

  it('rejects dropping onto itself', () => {
    const res = manager().resolve(subject({ id: 'blk2', kind: 'block', parentId: 'sec1', index: 1 }), 200, 100)
    expect(res.valid).toBe(false)
    expect(res.reason).toMatch(/itself/i)
  })

  it('rejects dropping a container into its own child (cycle)', () => {
    const res = manager().resolve(subject({ id: 'ctr', kind: 'container', parentId: 'sec1', index: 2 }), 200, 200)
    expect(res.valid).toBe(false)
    expect(res.reason).toMatch(/child/i)
  })

  it('returns an invalid resolution when nothing is under the pointer', () => {
    const res = manager().resolve(subject({ id: 'blk1', kind: 'block' }), 200, 590)
    expect(res.valid).toBe(false)
    expect(res.reason).toMatch(/no drop target/i)
  })

  it('treats a block-kind marker as a container when the injected check says so', () => {
    // blk2 renders as kind 'block' (the renderer has no 'container' kind), but a
    // container check flags it — a drop from its middle band must land inside it.
    const m = manager()
    m.setContainerCheck((id) => id === 'blk2')
    const res = m.resolve(subject({ id: 'blk1', kind: 'block', parentId: 'sec1', index: 0 }), 200, 100)
    expect(res).toMatchObject({ valid: true, edge: 'inside', targetId: 'blk2', parentId: 'blk2' })
  })
})

describe('DropManager — section drops', () => {
  it('reorders a section relative to another root section', () => {
    const res = manager().resolve(subject({ id: 'sec1', kind: 'section', parentId: null, index: 0 }), 200, 330)
    expect(res).toMatchObject({ valid: true, targetId: 'sec2', referenceId: 'sec2', parentId: null })
    expect(res.edge === 'before' || res.edge === 'after').toBe(true)
  })

  it('snaps to the root section when hovering a nested block', () => {
    const res = manager().resolve(subject({ id: 'sec2', kind: 'section', parentId: null, index: 1 }), 200, 100)
    // Hovering blk2 (inside sec1) resolves to sec1 at the root.
    expect(res).toMatchObject({ valid: true, referenceId: 'sec1', parentId: null })
  })

  it('rejects reordering a section over its own subtree', () => {
    const res = manager().resolve(subject({ id: 'sec1', kind: 'section', parentId: null, index: 0 }), 200, 100)
    expect(res.valid).toBe(false)
    expect(res.reason).toMatch(/root/i)
  })
})

// Phase 25.3.2 §10 — composition nesting policy (mirrors the server).
const COMPOSITION_TREE: MarkerSpec[] = [
  {
    id: 'sec1',
    kind: 'section',
    type: 'section',
    rect: rect(0, 0, 400, 300),
    children: [
      {
        id: 'con1',
        kind: 'container',
        type: 'container',
        rect: rect(10, 10, 380, 280),
        children: [{ id: 'col1', kind: 'block', type: 'column', rect: rect(20, 20, 360, 260), children: [] }],
      },
    ],
  },
]

function compositionManager() {
  const s = scene(COMPOSITION_TREE)
  const m = new DropManager(s.geometry, s.hitTest)
  m.setContainerCheck((id) => id === 'con1' || id === 'col1')
  return m
}

describe('DropManager — composition nesting policy', () => {
  // x=12 hits the Container's padding (left of col1 at x=20), y=150 is its middle
  // band → an inside-drop targeting con1 (not the col1 it wraps).
  it('rejects an element dropped straight into a Container', () => {
    const res = compositionManager().resolve(subject({ id: 'x', kind: 'block', type: 'button', parentId: null, index: 0 }), 12, 150)
    expect(res.parentId).toBe('con1')
    expect(res.valid).toBe(false)
    expect(res.reason).toMatch(/button.*container/i)
  })

  it('allows a Column into a Container', () => {
    const res = compositionManager().resolve(subject({ id: 'x', kind: 'block', type: 'column', parentId: null, index: 0 }), 12, 150)
    expect(res).toMatchObject({ valid: true, parentId: 'con1' })
  })

  it('rejects a Column dropped into a Column', () => {
    const res = compositionManager().resolve(subject({ id: 'x', kind: 'block', type: 'column', parentId: null, index: 0 }), 200, 150)
    expect(res.parentId).toBe('col1')
    expect(res.valid).toBe(false)
  })

  it('allows an element into a Column', () => {
    const res = compositionManager().resolve(subject({ id: 'x', kind: 'block', type: 'text', parentId: null, index: 0 }), 200, 150)
    expect(res).toMatchObject({ valid: true, parentId: 'col1' })
  })
})

// Phase 26.3.7 — nearest valid parent. The blessed SampleLayout model puts `column`
// blocks DIRECTLY inside a permissive `columns` section (no container wrapper), so a
// bare before/after on a column's edge used to drop an element as a SIBLING of the
// columns (a direct child of the section) — rendering it outside the layout. An
// element must land INSIDE the column instead; columns keep sibling semantics.
const COLUMNS_SECTION_TREE: MarkerSpec[] = [
  {
    id: 'cols',
    kind: 'section',
    type: 'columns',
    rect: rect(0, 0, 400, 300),
    children: [
      { id: 'col1', kind: 'block', type: 'column', rect: rect(0, 0, 200, 300), children: [] },
      { id: 'col2', kind: 'block', type: 'column', rect: rect(200, 0, 200, 300), children: [] },
    ],
  },
]

function columnsManager() {
  const s = scene(COLUMNS_SECTION_TREE)
  const m = new DropManager(s.geometry, s.hitTest)
  m.setContainerCheck((id) => id === 'col1' || id === 'col2')
  return m
}

describe('DropManager — nearest valid parent (Phase 26.3.7)', () => {
  it('drops an element INSIDE a column when dropped on its top edge (not as a sibling of the columns)', () => {
    const res = columnsManager().resolve(subject({ id: 'x', kind: 'block', type: 'text', parentId: null, index: 0 }), 100, 30)
    expect(res).toMatchObject({ valid: true, parentId: 'col1', edge: 'inside' })
  })

  it('drops an element INSIDE a column when dropped on its bottom edge', () => {
    const res = columnsManager().resolve(subject({ id: 'x', kind: 'block', type: 'text', parentId: null, index: 0 }), 100, 270)
    expect(res).toMatchObject({ valid: true, parentId: 'col1', edge: 'inside' })
  })

  it('keeps a column dropped on a column edge as a sibling (reorder columns in the section)', () => {
    const res = columnsManager().resolve(subject({ id: 'x', kind: 'block', type: 'column', parentId: null, index: 0 }), 100, 30)
    expect(res).toMatchObject({ valid: true, parentId: 'cols', edge: 'before' })
  })
})
