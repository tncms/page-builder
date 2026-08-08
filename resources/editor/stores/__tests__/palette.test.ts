import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import type { LayoutPayload, EditorMetadata } from '../../types'

const metadata = {
  version: '1',
  sections: [{ type: 'hero', label: 'Hero', defaults: { heading: 'Hi' } }],
  blocks: [{ type: 'button', label: 'Button', defaults: { text: 'Go' } }],
  tokens: {},
} as unknown as EditorMetadata

// sec1 [ col(container) [ btn ] ]
function payload(): LayoutPayload {
  return {
    version: 1,
    sections: [
      {
        id: 'sec1',
        type: 'hero',
        data: {},
        blocks: [{ id: 'col', type: 'columns', data: { blocks: [{ id: 'btn', type: 'button', data: {} }] } }],
      },
    ],
  } as unknown as LayoutPayload
}

describe('editor store — palette add actions (Phase 14.2)', () => {
  let store: ReturnType<typeof useEditorStore>
  let runCommand: ReturnType<typeof vi.fn>

  beforeEach(() => {
    // Simulate the browser: structuredClone throws DataCloneError on reactive
    // proxies. The clone path must NOT depend on it (Phase 14.2 hotfix).
    vi.stubGlobal('structuredClone', () => {
      throw new Error("Failed to execute 'structuredClone': could not be cloned.")
    })
    setActivePinia(createPinia())
    store = useEditorStore()
    store.currentPayload = payload()
    store.metadata = metadata
    // Actually apply the insert so the payload CHANGES — that's what makes
    // executeCommand record history (pushHistory → clonePayload), the code path
    // that used to throw on structuredClone.
    runCommand = vi.fn(async (before: LayoutPayload, _cmd: string, params: Record<string, any>) => {
      const p = JSON.parse(JSON.stringify(before))
      const node = { ...params.component, id: `new-${params.component.type}` }
      if (params.target_parent_id == null) p.sections.push(node)
      return { payload: p, selection: { selected: node.id } }
    })
    ;(store as unknown as { client: unknown }).client = { runCommand }
  })

  it('addSectionFromPalette does not throw a structuredClone error and inserts', async () => {
    // Regression: pushHistory used to structuredClone the reactive payload and
    // throw DataCloneError, swallowing the insert. It must succeed now.
    await expect(store.addSectionFromPalette(metadata.sections[0])).resolves.toBeUndefined()
    expect(store.commandError).toBeNull()
    expect((store.currentPayload as any).sections.map((s: any) => s.id)).toContain('new-hero')
    expect(store.canUndo).toBe(true) // history was recorded — clone worked
  })

  it('addSectionFromPalette inserts a section at the root end via the command API', async () => {
    await store.addSectionFromPalette(metadata.sections[0])
    expect(runCommand).toHaveBeenCalledTimes(1)
    const [, command, params] = runCommand.mock.calls[0]
    expect(command).toBe('insert-component')
    expect(params).toMatchObject({ target_parent_id: null, index: 1 })
    expect(params.component).toEqual({ type: 'hero', data: { heading: 'Hi' }, blocks: [] })
  })

  it('addBlockFromPalette errors when nothing is selected', async () => {
    await store.addBlockFromPalette(metadata.blocks[0])
    expect(runCommand).not.toHaveBeenCalled()
    expect(store.commandError).toBe('Select a section or container to add this block.')
  })

  it('adds a block INTO a selected section', async () => {
    store.selectComponent('sec1')
    await store.addBlockFromPalette(metadata.blocks[0])
    const [, command, params] = runCommand.mock.calls[0]
    expect(command).toBe('insert-component')
    expect(params).toMatchObject({ target_parent_id: 'sec1', index: 1 }) // sec1 already has 1 child
    expect(store.commandError).toBeNull()
  })

  it('adds a block INTO a selected container block', async () => {
    store.selectComponent('col')
    await store.addBlockFromPalette(metadata.blocks[0])
    expect(runCommand.mock.calls[0][2]).toMatchObject({ target_parent_id: 'col', index: 1 })
  })

  it('adds a block as a SIBLING of a selected leaf block', async () => {
    store.selectComponent('btn')
    await store.addBlockFromPalette(metadata.blocks[0])
    // btn lives in col → new block goes into col
    expect(runCommand.mock.calls[0][2]).toMatchObject({ target_parent_id: 'col' })
  })

  // Phase 26.3.10: click-insert converges on the single guarded insertion entry
  // point insertComponent(), the same path the Navigator + "Add Element" use.
  it('addBlockFromPalette delegates to insertComponent()', async () => {
    store.selectComponent('col')
    const spy = vi.spyOn(store, 'insertComponent')
    await store.addBlockFromPalette(metadata.blocks[0])
    expect(spy).toHaveBeenCalledWith({ parentId: 'col', componentType: 'button' })
  })
})
