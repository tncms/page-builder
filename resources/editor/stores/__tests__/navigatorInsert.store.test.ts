import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import { useNavigatorLocksStore } from '../navigatorLocks'
import type { LayoutPayload, EditorMetadata } from '../../types'

// store.insertComponent (Increment 6): the single insertion command path the
// Navigator Add Inside/Before/After — and (Increment 7) drag — reuse. It resolves
// the definition + kind from metadata, pre-validates nesting/lock/dirty, and runs
// the existing insert-component command (one undo; server selects the new node).

const metadata = {
  version: '1',
  sections: [{ type: 'hero', label: 'Hero' }, { type: 'section', label: 'Section' }],
  blocks: [
    { type: 'container', label: 'Container' },
    { type: 'column', label: 'Column' },
    { type: 'heading', label: 'Heading', defaults: { level: 2 } },
    { type: 'text', label: 'Text' },
  ],
  tokens: {},
} as unknown as EditorMetadata

function payload(): LayoutPayload {
  return {
    version: 1,
    sections: [
      { id: 'sec', type: 'hero', data: {}, blocks: [{ id: 'b1', type: 'heading', data: {} }] },
      { id: 'sec2', type: 'section', data: {}, blocks: [{ id: 'ctr', type: 'container', data: { blocks: [] } }] },
    ],
  } as unknown as LayoutPayload
}

// A fake insert-component endpoint: appends the component (with a fresh id) at the
// requested index and returns it as the server selection — enough for the store's
// changed-check + selection mirror to behave like production.
function pushInto(nodes: any[], parentId: string | null, comp: any, index: number | null): boolean {
  if (parentId === null) return false
  for (const n of nodes) {
    if (n.id === parentId) {
      const list = n.blocks ?? (n.data.blocks ??= [])
      list.splice(index ?? list.length, 0, comp)
      return true
    }
    const kids = n.blocks ?? n.data?.blocks
    if (Array.isArray(kids) && pushInto(kids, parentId, comp, index)) return true
  }
  return false
}

function scene() {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = payload()
  store.metadata = metadata
  const runCommand = vi.fn(async (before: LayoutPayload, command: string, params: Record<string, unknown>) => {
    if (command !== 'insert-component') return { payload: before, selection: { selected: null } }
    const next = JSON.parse(JSON.stringify(before)) as any
    const comp = { ...(params.component as any), id: 'new1' }
    const index = params.index as number | null
    if (params.target_parent_id === null) {
      next.sections.splice(index ?? next.sections.length, 0, { blocks: [], ...comp })
    } else {
      pushInto(next.sections, params.target_parent_id as string, comp, index)
    }
    return { payload: next, selection: { selected: 'new1' } }
  })
  ;(store as unknown as { client: unknown }).client = { runCommand }
  return { store, runCommand }
}

describe('store.insertComponent', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('inserts a block into a section as one command, appended, and returns the new id', async () => {
    const { store, runCommand } = scene()
    const id = await store.insertComponent({ parentId: 'sec', componentType: 'heading' })
    expect(id).toBe('new1')
    expect(runCommand).toHaveBeenCalledTimes(1)
    const [, command, params] = runCommand.mock.calls[0]
    expect(command).toBe('insert-component')
    expect((params.component as any).type).toBe('heading')
    expect((params.component as any).data).toEqual({ level: 2 }) // definition defaults cloned in
    expect(params.target_parent_id).toBe('sec')
    expect(params.index).toBe(1) // appended after existing b1
    expect(store.undoStack.length).toBe(1)
    expect(store.selectedComponentId).toBe('new1')
  })

  it('honours an explicit index', async () => {
    const { store, runCommand } = scene()
    await store.insertComponent({ parentId: 'sec', componentType: 'text', index: 0 })
    expect(runCommand.mock.calls[0][2].index).toBe(0)
  })

  it('inserts a section at the root (kind resolved from the section registry)', async () => {
    const { store, runCommand } = scene()
    const id = await store.insertComponent({ parentId: null, componentType: 'hero' })
    expect(id).toBe('new1')
    const params = runCommand.mock.calls[0][2]
    expect((params.component as any).blocks).toEqual([]) // section payload carries blocks[]
    expect(params.target_parent_id).toBeNull()
    expect(params.index).toBe(2) // appended after the two root sections
  })

  it('rejects an unknown type without a command', async () => {
    const { store, runCommand } = scene()
    expect(await store.insertComponent({ parentId: 'sec', componentType: 'nope' })).toBeNull()
    expect(runCommand).not.toHaveBeenCalled()
  })

  it('rejects a block at the root and a section inside a parent (kind mismatch)', async () => {
    const { store, runCommand } = scene()
    expect(await store.insertComponent({ parentId: null, componentType: 'heading' })).toBeNull()
    expect(await store.insertComponent({ parentId: 'sec', componentType: 'hero' })).toBeNull()
    expect(runCommand).not.toHaveBeenCalled()
  })

  it('rejects a nesting-disallowed pairing (heading into a container)', async () => {
    const { store, runCommand } = scene()
    expect(await store.insertComponent({ parentId: 'ctr', componentType: 'heading' })).toBeNull()
    expect(runCommand).not.toHaveBeenCalled()
    // But a column IS allowed inside a container.
    expect(await store.insertComponent({ parentId: 'ctr', componentType: 'column' })).toBe('new1')
  })

  it('never inserts into a locked parent', async () => {
    const { store, runCommand } = scene()
    useNavigatorLocksStore().lock(store.owner, 'sec')
    expect(await store.insertComponent({ parentId: 'sec', componentType: 'heading' })).toBeNull()
    expect(runCommand).not.toHaveBeenCalled()
  })

  it('aborts when the inspector dirty-guard blocks it', async () => {
    const { store, runCommand } = scene()
    store.setInspectorDirty(true)
    store.registerInspectorGuard(async () => false)
    expect(await store.insertComponent({ parentId: 'sec', componentType: 'heading' })).toBeNull()
    expect(runCommand).not.toHaveBeenCalled()
  })
})

describe('store.expandComponent', () => {
  it('removes a node from the collapsed set (no-op when already expanded)', () => {
    setActivePinia(createPinia())
    const store = useEditorStore()
    store.toggleCollapse('x') // now collapsed
    expect(store.isCollapsed('x')).toBe(true)
    store.expandComponent('x')
    expect(store.isCollapsed('x')).toBe(false)
    store.expandComponent('x') // idempotent
    expect(store.isCollapsed('x')).toBe(false)
  })
})
