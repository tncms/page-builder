import { describe, it, expect } from 'vitest'
import { validateLayout } from '../layoutValidation'
import type { EditorMetadata, LayoutPayload } from '../../types'

// Pure layout-integrity tests (Increment 7). The save gate depends on these codes,
// so the structural audit is pinned independently of the store.

const metadata = {
  version: '1',
  sections: [{ type: 'hero', label: 'Hero' }],
  blocks: [
    { type: 'container', label: 'Container' },
    { type: 'column', label: 'Column' },
    { type: 'text', label: 'Text' },
    { type: 'button', label: 'Button' },
  ],
  tokens: {},
} as unknown as EditorMetadata

// A structurally sound composition: Section → Container → Column → [Text, Button].
function goodPayload(): LayoutPayload {
  return {
    schema: 'x',
    sections: [
      {
        id: 'sec',
        type: 'hero',
        data: {},
        blocks: [
          {
            id: 'ctr',
            type: 'container',
            data: { blocks: [{ id: 'col', type: 'column', data: { blocks: [{ id: 't', type: 'text', data: {} }, { id: 'b', type: 'button', data: {} }] } }] },
          },
        ],
      },
    ],
  } as unknown as LayoutPayload
}

const codes = (p: LayoutPayload, m?: EditorMetadata) => validateLayout(p, m).map((i) => i.code)

describe('validateLayout', () => {
  it('passes a sound tree (with and without metadata)', () => {
    expect(validateLayout(goodPayload(), metadata)).toEqual([])
    expect(validateLayout(goodPayload())).toEqual([])
  })

  it('flags a corrupted root', () => {
    expect(codes({ sections: null } as unknown as LayoutPayload)).toEqual(['corrupt-root'])
  })

  it('flags duplicate ids', () => {
    const p = goodPayload() as any
    p.sections[0].blocks[0].id = 'sec' // clash with the section id
    expect(codes(p)).toContain('duplicate-id')
  })

  it('flags a missing/blank id and a missing type', () => {
    const p = goodPayload() as any
    p.sections[0].blocks[0].id = ''
    p.sections[0].blocks[0].data.blocks[0].type = ''
    const c = codes(p)
    expect(c).toContain('missing-id')
    expect(c).toContain('missing-type')
  })

  it('flags a corrupted children array', () => {
    const p = goodPayload() as any
    p.sections[0].blocks = 'nope'
    expect(codes(p)).toContain('corrupt-children')
  })

  it('flags invalid nesting (column inside column)', () => {
    const p = goodPayload() as any
    // Put a column directly inside the existing column → column cannot hold a column.
    p.sections[0].blocks[0].data.blocks[0].data.blocks.push({ id: 'col2', type: 'column', data: { blocks: [] } })
    expect(codes(p)).toContain('invalid-nesting')
  })

  it('flags an unknown type only when metadata is provided', () => {
    const p = goodPayload() as any
    p.sections[0].blocks[0].data.blocks[0].data.blocks[0].type = 'widget'
    expect(codes(p, metadata)).toContain('unknown-type')
    expect(codes(p)).not.toContain('unknown-type') // permissive without a registry
  })
})
