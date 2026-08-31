import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import type { EditorContextData, LayoutPayload } from '../../types'

// PB-TEMPLATE-REGISTRY-2-D (§14/§15) — client-side history (undo/redo snapshots) is payload-based
// and type-agnostic, so an unknown node is preserved byte-for-byte across a known-sibling edit,
// undo, and redo. This locks that a future known-type filter/normalizer in the history path
// cannot silently drop unknown content.

function unknownSection(): Record<string, unknown> {
  return {
    id: 'u-1', type: 'theme-mystery', data: { keep: 'me', nested: { arr: [1, false, null, 'x'] } },
    blocks: [{ id: 'ub-1', type: 'exotic-block', data: { note: 'nested' }, blocks: [] }],
  }
}

function context(): EditorContextData {
  return {
    owner: { type: 'page', id: 1 },
    layout: {
      version_token: 't1', is_published_current: false, has_draft: true, has_published: false,
      draft_payload: { schema: 'v1', sections: [unknownSection(), { id: 'k-1', type: 'hero', data: { title: 'A' }, blocks: [] }] },
      published_payload: null,
    },
    metadata: { version: '1', sections: [], blocks: [], tokens: {} },
    permissions: { can_publish: true },
  } as unknown as EditorContextData
}

function fakeRunCommand() {
  return vi.fn(async (payload: LayoutPayload, command: string, params: Record<string, unknown>) => {
    const next = JSON.parse(JSON.stringify(payload)) as { sections: Array<Record<string, unknown>> }
    if (command === 'update-component-data') {
      const t = next.sections.find((s) => s.id === params.component_id)
      if (t) t.data = { ...(t.data as object), ...(params.data as object) }
    }
    return { payload: next, selection: { selected: (params.component_id as string) ?? null } }
  })
}

function scene() {
  setActivePinia(createPinia())
  const store = useEditorStore()
  ;(store as unknown as { client: unknown }).client = { runCommand: fakeRunCommand() }
  store.applyContext(context())
  return store
}

function nodeById(store: ReturnType<typeof useEditorStore>, id: string) {
  return store.currentPayload.sections.find((s) => (s as unknown as Record<string, unknown>).id === id)
}

beforeEach(() => vi.useFakeTimers())
afterEach(() => { useEditorStore().teardownUnloadGuard?.(); vi.useRealTimers() })

describe('undo / redo preserve an unknown node losslessly', () => {
  it('a known-sibling edit followed by undo then redo keeps the unknown node byte-identical', async () => {
    const store = scene()
    const pristine = unknownSection()

    await store.updateComponentDataById('k-1', { title: 'B' })
    expect((nodeById(store, 'k-1') as Record<string, unknown>).data).toMatchObject({ title: 'B' })
    expect(nodeById(store, 'u-1')).toEqual(pristine)

    expect(store.canUndo).toBe(true)
    store.undo()
    // The known sibling reverts; the unknown node is still intact.
    expect((nodeById(store, 'k-1') as Record<string, unknown>).data).toMatchObject({ title: 'A' })
    expect(nodeById(store, 'u-1')).toEqual(pristine)

    expect(store.canRedo).toBe(true)
    store.redo()
    expect((nodeById(store, 'k-1') as Record<string, unknown>).data).toMatchObject({ title: 'B' })
    expect(nodeById(store, 'u-1')).toEqual(pristine)
  })
})
