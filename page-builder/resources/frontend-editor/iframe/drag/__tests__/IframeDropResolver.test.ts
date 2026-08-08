import { describe, it, expect, afterEach } from 'vitest'
import { IframeDropResolver } from '../IframeDropResolver'
import { standardCanvas, STANDARD_CONTAINERS } from './dom'
import type { DragSubject } from '../types'

afterEach(() => {
  document.body.innerHTML = ''
})

function resolver(): IframeDropResolver {
  const canvas = standardCanvas()
  return new IframeDropResolver(canvas, { isContainer: (id) => STANDARD_CONTAINERS.includes(id) })
}

function subject(over: Partial<DragSubject>): DragSubject {
  return { id: 'x', kind: 'block', type: 't', parentId: null, index: 0, ...over }
}

describe('section drags', () => {
  it('reorders at the root against the nearest root section', () => {
    const res = resolver().resolve(subject({ id: 's1', kind: 'section', type: 'hero' }), 250, 380)
    expect(res).toMatchObject({ valid: true, edge: 'after', parentId: null, referenceId: 's3' })
  })

  it('rejects dropping a section onto itself', () => {
    const res = resolver().resolve(subject({ id: 's3', kind: 'section', type: 'cta' }), 250, 320)
    expect(res.valid).toBe(false)
  })
})

describe('block drags', () => {
  it('drops inside a container (including an empty one)', () => {
    const res = resolver().resolve(subject({ id: 'b1', kind: 'block', parentId: 'colA', index: 0 }), 300, 200)
    expect(res).toMatchObject({ valid: true, edge: 'inside', parentId: 'colB', referenceId: 'colB' })
  })

  it('drops before/after a leaf into the leaf’s parent', () => {
    const res = resolver().resolve(subject({ id: 'p', source: 'palette' }), 100, 115)
    expect(res).toMatchObject({ valid: true, edge: 'before', parentId: 'colA', referenceId: 'b1' })
  })

  it('rejects a block dropped in an empty gap (no root drop for blocks)', () => {
    const res = resolver().resolve(subject({ id: 'p', source: 'palette' }), 250, 500)
    expect(res.valid).toBe(false)
    expect(res.reason).toBe('No drop target')
  })

  it('rejects dropping a container into its own descendant', () => {
    const res = resolver().resolve(subject({ id: 'colA', kind: 'block', parentId: 's2', index: 0 }), 100, 130)
    expect(res.valid).toBe(false)
    expect(res.reason).toBe('Cannot drop into its own child')
    expect(res.markerRect).not.toBeNull() // rect present so the shell can paint red
  })

  it('rejects dropping onto itself', () => {
    const res = resolver().resolve(subject({ id: 'b1', kind: 'block', parentId: 'colA', index: 0 }), 100, 130)
    expect(res.valid).toBe(false)
  })
})

// Phase 26.3.8: drop-acceptance hardening. Fixture s2 (columns 0–500 × 100–300)
// holds colA (0–250, with child b1 at 110–150) and empty colB (250–500). colB's
// vertical thirds land at ~166.7 and ~233.3.
describe('live column drop acceptance (Phase 26.3.8)', () => {
  const palette = (type: string): Partial<DragSubject> => ({
    id: 'new',
    kind: 'block',
    type,
    parentId: null,
    index: 0,
    source: 'palette',
  })

  it('drops an element on the TOP edge of an empty column INSIDE that column', () => {
    // Top third → edge "before"; the nested-container redirect must keep it inside colB.
    const res = resolver().resolve(subject(palette('image')), 300, 120)
    expect(res).toMatchObject({ valid: true, edge: 'inside', parentId: 'colB', referenceId: 'colB' })
    // Never a sibling of the Columns wrapper.
    expect(res.parentId).not.toBe('s2')
  })

  it('drops an element on the BOTTOM edge of an empty column INSIDE that column', () => {
    const res = resolver().resolve(subject(palette('heading')), 300, 280)
    expect(res).toMatchObject({ valid: true, edge: 'inside', parentId: 'colB' })
    expect(res.parentId).not.toBe('s2')
  })

  it('drops an element in the empty area of a populated column INSIDE that column', () => {
    // (100, 280): below child b1 but still inside colA.
    const res = resolver().resolve(subject(palette('button')), 100, 280)
    expect(res).toMatchObject({ valid: true, edge: 'inside', parentId: 'colA' })
    expect(res.parentId).not.toBe('s2')
  })

  it('multiple columns: an element dropped over column B lands in B, never beside the Columns', () => {
    const res = resolver().resolve(subject(palette('text')), 300, 200)
    expect(res.valid).toBe(true)
    expect(res.parentId).toBe('colB')
    expect(res.parentId).not.toBe('s2')
  })

  it('rejects dropping a column inside another column', () => {
    const move = subject({ id: 'colA', kind: 'block', type: 'column', parentId: 's2', index: 0 })
    const res = resolver().resolve(move, 300, 200)
    expect(res.valid).toBe(false)
    expect(res.reason).toBe('A column cannot go inside a column')
  })

  // Phase 26.3.14 — the differential root cause: a SECTION (e.g. the "Image
  // Section", type "image-section") dragged over a column CANNOT nest — it snaps
  // to the ROOT, before/after the containing section. This is why the wrong
  // palette card made "Image → Column" appear to "fall outside the column",
  // while the Image BLOCK (kind:block) lands inside. Contrast with the block
  // cases above which resolve parentId:'colB'.
  it('a SECTION dropped over a column snaps to the ROOT, never inside the column', () => {
    const section = subject({ id: 'new', kind: 'section', type: 'image-section', parentId: null, index: 0, source: 'palette' })
    const res = resolver().resolve(section, 300, 200) // over empty colB
    expect(res.valid).toBe(true)
    expect(res.parentId).toBeNull() // ROOT — not colB
    expect(res.referenceId).toBe('s2') // the containing Columns section
  })
})
