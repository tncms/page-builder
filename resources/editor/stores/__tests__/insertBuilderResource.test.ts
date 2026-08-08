// Phase 26.47E — store.insertBuilderResource fetches a design resource's id-free
// payload and dispatches it through the EXISTING insert-pattern / insert-component
// commands (no second insertion engine). The picker event bridge routes the
// 26.47B insert-resource intent to the same path.
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import { wireBuilderResourceInsertion } from '../../insert/builderResourceInsertion'
import type { LayoutPayload } from '../../types'

function makeStore(getBuilderResourcePayload: ReturnType<typeof vi.fn>, runCommand: ReturnType<typeof vi.fn>) {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = { schema: 'pagebuilder/01', sections: [] } as unknown as LayoutPayload
  store.client = { runCommand, getBuilderResourcePayload } as never
  return store
}

describe('store.insertBuilderResource (Phase 26.47E)', () => {
  let runCommand: ReturnType<typeof vi.fn>

  beforeEach(() => {
    runCommand = vi.fn(async (before: LayoutPayload) => ({ payload: before, selection: { selected: null } }))
  })

  it('inserts a section resource via the existing insert-pattern command', async () => {
    const sections = [{ type: 'hero', id: null, data: {}, blocks: [] }]
    const getPayload = vi.fn(async () => ({ rid: 'builtin:section:hero', kind: 'section', sections, block: null }))
    const store = makeStore(getPayload, runCommand)

    await store.insertBuilderResource({ rid: 'builtin:section:hero', kind: 'section' })

    expect(getPayload).toHaveBeenCalledWith('builtin:section:hero')
    const [, command, params] = runCommand.mock.calls[0]
    expect(command).toBe('insert-pattern')
    expect(params).toEqual({ sections })
  })

  it('inserts a block resource into the current container via insert-component', async () => {
    const block = { type: 'text', id: null, data: { text: 'hi' } }
    const getPayload = vi.fn(async () => ({
      rid: 'builtin:block:text',
      kind: 'block',
      sections: [{ type: 'generic', id: null, data: {}, blocks: [block] }],
      block,
    }))
    const store = makeStore(getPayload, runCommand)
    vi.spyOn(store, 'blockInsertParent').mockReturnValue('s1')

    await store.insertBuilderResourceByRid('builtin:block:text', 'block')

    const [, command, params] = runCommand.mock.calls[0]
    expect(command).toBe('insert-component')
    expect((params as Record<string, unknown>).target_parent_id).toBe('s1')
    expect((params as Record<string, unknown>).component).toEqual(block)
  })

  it('falls back to insert-pattern for a block with no container selected', async () => {
    const block = { type: 'text', id: null, data: {} }
    const sections = [{ type: 'generic', id: null, data: {}, blocks: [block] }]
    const getPayload = vi.fn(async () => ({ rid: 'builtin:block:text', kind: 'block', sections, block }))
    const store = makeStore(getPayload, runCommand)
    vi.spyOn(store, 'blockInsertParent').mockReturnValue(null)

    await store.insertBuilderResourceByRid('builtin:block:text', 'block')

    expect(runCommand.mock.calls[0][1]).toBe('insert-pattern')
  })

  it('sets an error and dispatches nothing for an empty payload', async () => {
    const getPayload = vi.fn(async () => ({ rid: 'x:section:y', kind: 'section', sections: [], block: null }))
    const store = makeStore(getPayload, runCommand)

    const ok = await store.insertBuilderResource({ rid: 'x:section:y', kind: 'section' })

    expect(ok).toBe(false)
    expect(runCommand).not.toHaveBeenCalled()
    expect(store.commandError).toBeTruthy()
  })
})

describe('wireBuilderResourceInsertion (Phase 26.47E)', () => {
  it('routes a page-builder:insert-resource event to native insertion', () => {
    setActivePinia(createPinia())
    const store = useEditorStore()
    const spy = vi.spyOn(store, 'insertBuilderResourceByRid').mockResolvedValue(true)
    const off = wireBuilderResourceInsertion(store)

    document.dispatchEvent(
      new CustomEvent('page-builder:insert-resource', { detail: { rid: 'builtin:section:hero', kind: 'section' } }),
    )

    expect(spy).toHaveBeenCalledWith('builtin:section:hero', 'section')
    off()
  })

  it('ignores an event with an unsupported kind', () => {
    setActivePinia(createPinia())
    const store = useEditorStore()
    const spy = vi.spyOn(store, 'insertBuilderResourceByRid').mockResolvedValue(true)
    const off = wireBuilderResourceInsertion(store)

    document.dispatchEvent(
      new CustomEvent('page-builder:insert-resource', { detail: { rid: 'builtin:page:sample', kind: 'page' } }),
    )

    expect(spy).not.toHaveBeenCalled()
    off()
  })
})
