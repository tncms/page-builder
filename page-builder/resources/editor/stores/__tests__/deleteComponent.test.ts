// HOTFIX — the single delete action's selection fallback + structural refresh.
import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import type { LayoutPayload, StateResult } from '../../types'

function payload(): LayoutPayload {
  return {
    version: 1,
    sections: [
      { id: 's1', type: 'section', data: {}, blocks: [
        { id: 'b1', type: 'text', data: {} },
        { id: 'b2', type: 'text', data: {} },
        { id: 'b3', type: 'text', data: {} },
      ] },
      { id: 's2', type: 'section', data: {}, blocks: [] },
    ],
  } as unknown as LayoutPayload
}

function removeFromPayload(p: LayoutPayload, id: string): LayoutPayload {
  const clone = JSON.parse(JSON.stringify(p)) as LayoutPayload
  clone.sections = (clone.sections ?? [])
    .filter((s: any) => s.id !== id)
    .map((s: any) => ({ ...s, blocks: (s.blocks ?? []).filter((b: any) => b.id !== id) }))
  return clone
}

function makeStore() {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = payload()
  store.client = {
    async runCommand(before: LayoutPayload, command: string, params: Record<string, unknown>): Promise<StateResult> {
      if (command === 'remove-component') {
        return { payload: removeFromPayload(before, params.component_id as string), selection: { selected: null } }
      }
      return { payload: before, selection: { selected: null } } as StateResult
    },
  } as never
  return store
}

describe('store.deleteComponent — fallback + structural refresh', () => {
  let store: ReturnType<typeof useEditorStore>
  beforeEach(() => { store = makeStore() })

  it('falls back to the NEXT sibling', async () => {
    store.selectComponent('b2')
    await store.deleteComponent('b2')
    expect(store.selectedComponentId).toBe('b3')
  })

  it('falls back to the PREVIOUS sibling when last', async () => {
    store.selectComponent('b3')
    await store.deleteComponent('b3')
    expect(store.selectedComponentId).toBe('b2')
  })

  it('falls back to the PARENT when the only child is removed', async () => {
    store.selectComponent('b1')
    await store.deleteComponent('b1') // b2,b3 remain → next is b2
    expect(store.selectedComponentId).toBe('b2')
  })

  it('falls back to NULL when the last root section is removed', async () => {
    // Remove s1 (s2 remains) → next sibling s2; then remove s2 → null.
    store.selectComponent('s1')
    await store.deleteComponent('s1')
    expect(store.selectedComponentId).toBe('s2')
    await store.deleteComponent('s2')
    expect(store.selectedComponentId).toBeNull()
  })

  it('preserves the current selection when deleting a DIFFERENT node that survives', async () => {
    store.selectComponent('b1')
    await store.deleteComponent('b3') // b1 still exists
    expect(store.selectedComponentId).toBe('b1')
  })

  it('clears the reconcile directive (delete is structural, needs a full refresh)', async () => {
    store.reconcileComponentId = 'stale-from-prior-edit'
    store.selectComponent('b2')
    await store.deleteComponent('b2')
    expect(store.reconcileComponentId).toBeNull()
  })

  it('is a safe no-op for a missing id (payload + selection unchanged)', async () => {
    store.selectComponent('b2')
    const before = JSON.stringify(store.currentPayload)
    await store.deleteComponent('does-not-exist')
    expect(JSON.stringify(store.currentPayload)).toBe(before)
    expect(store.selectedComponentId).toBe('b2')
  })
})
