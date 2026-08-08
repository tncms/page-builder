import { describe, it, expect } from 'vitest'
import { DropManager } from '../DropManager'
import { scene, rect, subject, type MarkerSpec } from './helpers'

// Phase 25.3 — composition nesting: a Section holds a Container; the Container holds
// Columns; Columns hold element blocks. This verifies the EXISTING drag system
// understands the new layout nodes (Container / Column render as kind 'container').
const TREE: MarkerSpec[] = [
  {
    id: 'sec',
    kind: 'section',
    type: 'section',
    rect: rect(0, 0, 400, 300),
    children: [
      {
        id: 'ctr',
        kind: 'container',
        rect: rect(10, 10, 380, 260),
        children: [
          { id: 'col1', kind: 'container', rect: rect(20, 20, 170, 240), children: [] },
          { id: 'col2', kind: 'container', rect: rect(210, 20, 170, 240), children: [] },
        ],
      },
    ],
  },
]

function manager() {
  const s = scene(TREE)
  return new DropManager(s.geometry, s.hitTest)
}

describe('layout composition nesting (Phase 25.3)', () => {
  it('accepts an element dropped inside a Column', () => {
    const res = manager().resolve(subject({ id: 'heading', kind: 'block' }), 100, 130)
    expect(res.valid).toBe(true)
    expect(res.parentId).toBe('col1')
  })

  it('accepts a Column dropped inside a Container', () => {
    // Drop near the container edge (x < 20) so it lands in the container, not a column.
    const res = manager().resolve(subject({ id: 'newcol', kind: 'container' }), 14, 130)
    expect(res.valid).toBe(true)
    expect(res.parentId).toBe('ctr')
  })

  it('rejects nesting a Container into its own descendant (invalid nesting)', () => {
    const res = manager().resolve(subject({ id: 'ctr', kind: 'container', parentId: 'sec', index: 0 }), 100, 130)
    expect(res.valid).toBe(false)
    expect(res.reason).toMatch(/child|itself/i)
  })
})
