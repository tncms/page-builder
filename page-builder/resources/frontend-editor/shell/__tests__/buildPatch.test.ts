import { describe, it, expect } from 'vitest'
import { buildPatchMessages } from '../buildPatch'
import { SHELL_TO_IFRAME } from '../../bridge/messages'
import type { EditorMetadata, LivePatch } from '../../../editor/types'

function patch(part: Partial<LivePatch>): LivePatch {
  return { componentId: 'cta-1', type: 'cta', field: 'title', value: 'x', ...part }
}

const metadata = {
  version: '0',
  sections: [],
  blocks: [],
  tokens: { color: { primary: { value: '#ff0000' } } },
  breakpoints: {},
} as unknown as EditorMetadata

describe('patch message types are on the shell->iframe allow-list', () => {
  it('registers all pb:patch-* types', () => {
    for (const type of ['pb:patch-style', 'pb:patch-text', 'pb:patch-attr', 'pb:clear-patches']) {
      expect(SHELL_TO_IFRAME).toContain(type)
    }
  })
})

describe('buildPatchMessages', () => {
  it('builds a text patch for a mapped text field', () => {
    const msgs = buildPatchMessages(patch({ field: 'title', value: 'Hello' }), metadata)
    expect(msgs).toEqual([
      { type: 'pb:patch-text', payload: { componentId: 'cta-1', selector: '.pb-cta__title', text: 'Hello' } },
    ])
  })

  it('builds an attr patch for button_url', () => {
    const msgs = buildPatchMessages(patch({ field: 'button_url', value: '/go' }), metadata)
    expect(msgs).toEqual([
      {
        type: 'pb:patch-attr',
        payload: { componentId: 'cta-1', selector: '.pb-cta__button', attr: 'href', value: '/go' },
      },
    ])
  })

  it('builds a style patch for align (text-align on root)', () => {
    const msgs = buildPatchMessages(patch({ field: 'align', value: 'center' }), metadata)
    expect(msgs).toEqual([
      { type: 'pb:patch-style', payload: { componentId: 'cta-1', selector: null, style: { textAlign: 'center' } } },
    ])
  })

  it('resolves a design-token colour reference for background', () => {
    const msgs = buildPatchMessages(patch({ field: 'background', value: 'color.primary' }), metadata)
    expect(msgs[0]).toEqual({
      type: 'pb:patch-style',
      payload: { componentId: 'cta-1', selector: null, style: { backgroundColor: '#ff0000' } },
    })
  })

  it('passes a raw colour through unchanged', () => {
    const msgs = buildPatchMessages(patch({ field: 'background', value: '#123456' }), metadata)
    expect(msgs[0].payload.style).toEqual({ backgroundColor: '#123456' })
  })

  it('falls back to the generic map for an unknown component type', () => {
    const msgs = buildPatchMessages(patch({ type: 'hero', field: 'align', value: 'right' }), metadata)
    expect(msgs[0]).toMatchObject({ type: 'pb:patch-style', payload: { style: { textAlign: 'right' } } })
  })

  it('returns no messages for a non-patchable field', () => {
    expect(buildPatchMessages(patch({ type: 'text-section', field: 'width', value: 'wide' }), metadata)).toEqual([])
    expect(buildPatchMessages(patch({ field: 'nonsense', value: 'x' }), metadata)).toEqual([])
  })

  it('returns no messages without a component id', () => {
    expect(buildPatchMessages(patch({ componentId: null }), metadata)).toEqual([])
  })
})
