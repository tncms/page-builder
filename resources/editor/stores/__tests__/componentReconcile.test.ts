import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import type { LayoutPayload } from '../../types'

function payload(): LayoutPayload {
  return {
    version: 1,
    sections: [
      { id: 'sec1', type: 'hero', data: { title: 'Hi' }, blocks: [{ id: 'btn1', type: 'button', data: { url: 'x' } }] },
    ],
  } as unknown as LayoutPayload
}

const previewResult = {
  component_id: 'sec1',
  html: '<div data-pb-component-id="sec1">rendered</div>',
  kind: 'section',
  type: 'hero',
  warnings: [],
}

describe('editor store — Live Component Runtime (Phase 21)', () => {
  let store: ReturnType<typeof useEditorStore>
  let runCommand: ReturnType<typeof vi.fn>
  let renderComponentPreview: ReturnType<typeof vi.fn>

  beforeEach(() => {
    vi.stubGlobal('structuredClone', () => {
      throw new Error('could not be cloned')
    })
    setActivePinia(createPinia())
    store = useEditorStore()
    store.currentPayload = payload()
    ;(store as unknown as { owner: unknown }).owner = { type: 'page', id: 7 }

    runCommand = vi.fn(async (before: LayoutPayload, cmd: string, params: Record<string, any>) => {
      const p = JSON.parse(JSON.stringify(before))
      if (cmd === 'update-component-data') {
        const node = params.component_id === 'sec1' ? p.sections[0] : p.sections[0].blocks[0]
        node.data = { ...node.data, ...params.data }
        return { payload: p, selection: { selected: null } }
      }
      // Structural command: drop the block so the payload differs.
      p.sections[0].blocks = []
      return { payload: p, selection: { selected: null } }
    })
    renderComponentPreview = vi.fn(async () => previewResult)
    ;(store as unknown as { client: unknown }).client = { runCommand, renderComponentPreview }
  })

  // ---- Reconcile directive (what the frontend canvas consumes) ----------

  it('marks a component-data commit for single-component reconciliation', async () => {
    store.selectComponent('sec1')
    await store.updateComponentDataById('sec1', { title: 'Hello' })

    // The directive names the edited component; consuming it is one-shot.
    expect(store.consumeReconcileDirective()).toBe('sec1')
    expect(store.consumeReconcileDirective()).toBeNull()
  })

  it('preserves the current selection across a component-data commit', async () => {
    store.selectComponent('sec1')
    await store.updateComponentDataById('sec1', { title: 'Hello' })

    expect(store.selectedComponentId).toBe('sec1')
  })

  it('sets NO reconcile directive for a structural command (full refresh path)', async () => {
    await store.executeCommand('remove-component', { component_id: 'btn1' })

    expect(store.consumeReconcileDirective()).toBeNull()
  })

  it('clears the directive when a commit produced no change', async () => {
    runCommand.mockResolvedValueOnce({ payload: payload(), selection: { selected: null } })
    await store.updateComponentDataById('sec1', { title: 'Hi' }) // identical -> no change

    expect(store.consumeReconcileDirective()).toBeNull()
  })

  // ---- Server component render (what the iframe swaps in) ----------------

  it('renders one component through the client and publishes the reconcile', async () => {
    const ok = await store.renderComponentPreview('sec1')

    expect(ok).toBe(true)
    expect(renderComponentPreview).toHaveBeenCalledWith(store.currentPayload, 'sec1', 'page', 7)
    expect(store.componentReconcile).toMatchObject({
      componentId: 'sec1',
      html: previewResult.html,
      kind: 'section',
      type: 'hero',
    })
    expect(store.componentReconcile?.version).toBe(1)
  })

  it('returns false and records the error when the render fails (fallback path)', async () => {
    renderComponentPreview.mockRejectedValueOnce(new Error('boom'))
    const ok = await store.renderComponentPreview('sec1')

    expect(ok).toBe(false)
    expect(store.componentReconcile).toBeNull()
    expect(store.componentReconcileError).toBe('boom')
  })

  it('returns false without a client/owner', async () => {
    ;(store as unknown as { owner: unknown }).owner = null
    expect(await store.renderComponentPreview('sec1')).toBe(false)
  })

  // ---- Live patch is untouched (typing still fires instantly) ------------

  it('still emits live patches to a subscriber while typing', () => {
    const seen: unknown[] = []
    store.registerLivePatch((patch) => seen.push(patch))
    store.emitLivePatch({ componentId: 'sec1', type: 'hero', field: 'title', value: 'typing' })

    expect(seen).toHaveLength(1)
  })
})
