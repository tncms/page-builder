import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import type { LayoutPayload, EditorMetadata } from '../../types'

function payload(): LayoutPayload {
  return {
    version: 1,
    sections: [
      {
        id: 'sec1',
        type: 'hero',
        data: {},
        blocks: [{ id: 'ctr', type: 'columns', data: { blocks: [{ id: 'btn', type: 'button', data: {} }] } }],
      },
    ],
  } as unknown as LayoutPayload
}

const metadata = {
  version: '1',
  sections: [{ type: 'hero', label: 'Hero', icon: 'heroicon-o-star' }],
  blocks: [
    { type: 'columns', label: 'Columns' },
    { type: 'button', label: 'Button' },
  ],
  tokens: {},
} as unknown as EditorMetadata

describe('editor store — navigator/breadcrumb helpers (Phase 15)', () => {
  let store: ReturnType<typeof useEditorStore>

  beforeEach(() => {
    setActivePinia(createPinia())
    store = useEditorStore()
    store.currentPayload = payload()
    store.metadata = metadata
  })

  it('selectedPath tracks the current selection', () => {
    expect(store.selectedPath).toEqual([])
    store.selectComponent('btn')
    expect(store.selectedPath.map((p) => p.id)).toEqual(['sec1', 'ctr', 'btn'])
  })

  it('definitionOf resolves labels by kind + type', () => {
    expect(store.definitionOf('section', 'hero')?.label).toBe('Hero')
    expect(store.definitionOf('block', 'button')?.label).toBe('Button')
    expect(store.definitionOf('block', 'missing')).toBeNull()
  })

  it('focusComponent selects the component (scroll is a safe no-op without geometry)', () => {
    store.focusComponent('ctr')
    expect(store.selectedComponentId).toBe('ctr')
  })

  it('scrollToComponent does not throw when the component is not rendered', () => {
    expect(() => store.scrollToComponent('btn')).not.toThrow()
  })
})
