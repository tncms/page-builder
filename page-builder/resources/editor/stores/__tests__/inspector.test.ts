import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import type { LayoutPayload, EditorMetadata } from '../../types'

const metadata = {
  version: '1',
  sections: [{ type: 'hero', label: 'Hero', schema: { tabs: [] } }],
  blocks: [{ type: 'button', label: 'Button', schema: { tabs: [] } }],
  tokens: {},
} as unknown as EditorMetadata

function payload(): LayoutPayload {
  return {
    version: 1,
    sections: [
      { id: 'sec1', type: 'hero', data: { align: 'center' }, blocks: [{ id: 'btn1', type: 'button', data: { url: 'x' } }] },
    ],
  } as unknown as LayoutPayload
}

describe('editor store — inspector getters/actions (Phase 15)', () => {
  let store: ReturnType<typeof useEditorStore>
  let runCommand: ReturnType<typeof vi.fn>

  beforeEach(() => {
    // Simulate the browser: structuredClone throws on reactive proxies.
    vi.stubGlobal('structuredClone', () => {
      throw new Error('could not be cloned')
    })
    setActivePinia(createPinia())
    store = useEditorStore()
    store.currentPayload = payload()
    store.metadata = metadata
    runCommand = vi.fn(async (before: LayoutPayload, _cmd: string, params: Record<string, any>) => {
      const p = JSON.parse(JSON.stringify(before))
      const node = params.component_id === 'sec1' ? p.sections[0] : p.sections[0].blocks[0]
      node.data = { ...node.data, ...params.data }
      return { payload: p, selection: { selected: null } } // update cmd clears selection
    })
    ;(store as unknown as { client: unknown }).client = { runCommand }
  })

  it('selectedDefinition resolves the schema-bearing definition', () => {
    store.selectComponent('btn1')
    expect(store.selectedDefinition?.type).toBe('button')
    expect(store.selectedDefinition?.schema).toBeDefined()
  })

  it('selectedData returns the component data map', () => {
    store.selectComponent('sec1')
    expect(store.selectedData).toEqual({ align: 'center' })
  })

  it('currentLocale falls back to vi', () => {
    expect(store.currentLocale).toBe('vi')
    store.boot = { locale: 'en' } as never
    expect(store.currentLocale).toBe('en')
  })

  it('updateComponentDataById commits, marks dirty, and PRESERVES selection', async () => {
    store.selectComponent('sec1')
    const changed = await store.updateComponentDataById('sec1', { align: 'left' })
    expect(changed).toBe(true)
    expect(runCommand.mock.calls[0][1]).toBe('update-component-data')
    expect((store.currentPayload as any).sections[0].data.align).toBe('left')
    expect(store.dirty).toBe(true)
    // update-component-data doesn't re-select; the store restores it.
    expect(store.selectedComponentId).toBe('sec1')
  })

  it('updateSelectedData targets the current selection', async () => {
    store.selectComponent('btn1')
    await store.updateSelectedData({ url: 'https://y.dev' })
    expect(runCommand.mock.calls[0][2].component_id).toBe('btn1')
    expect(store.selectedComponentId).toBe('btn1')
  })

  it('updateSelectedData is a no-op with no selection', async () => {
    const changed = await store.updateSelectedData({ x: 1 })
    expect(changed).toBe(false)
    expect(runCommand).not.toHaveBeenCalled()
  })
})
