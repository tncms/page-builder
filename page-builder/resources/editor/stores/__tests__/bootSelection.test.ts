import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import type { LayoutPayload } from '../../types'

function payload(): LayoutPayload {
  return {
    schema: 'pagebuilder/01',
    sections: [
      { id: 's1', type: 'hero', blocks: [] },
      { id: 's2', type: 'columns', blocks: [{ id: 'b1', type: 'text', data: {} }] },
    ],
  } as unknown as LayoutPayload
}

beforeEach(() => setActivePinia(createPinia()))

describe('applyBootSelection (Phase 22.2 selection handoff)', () => {
  it('selects the handed-off component when it exists', () => {
    const store = useEditorStore()
    store.$patch({ boot: { selected: 'b1' } as never, currentPayload: payload() })
    store.applyBootSelection()
    expect(store.selectedComponentId).toBe('b1')
  })

  it('ignores an invalid/stale selected id', () => {
    const store = useEditorStore()
    store.$patch({ boot: { selected: 'missing' } as never, currentPayload: payload() })
    store.applyBootSelection()
    expect(store.selectedComponentId).toBeNull()
  })

  it('does nothing when no selection is handed off', () => {
    const store = useEditorStore()
    store.$patch({ boot: {} as never, currentPayload: payload() })
    store.applyBootSelection()
    expect(store.selectedComponentId).toBeNull()
  })

  it('exposes nav links from the boot payload', () => {
    const store = useEditorStore()
    const nav = { structure: '/edit', live: '/frontend', public: '/page', classic: '/admin/edit' }
    store.$patch({ boot: { nav } as never })
    expect(store.navLinks).toEqual(nav)
  })
})
