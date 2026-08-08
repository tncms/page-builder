// Phase 26.1 — store.applyColumnLayout dispatches the apply-column-layout command
// (one undo entry) and keeps the container selected when it survives.
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import type { LayoutPayload } from '../../types'

function payload(): LayoutPayload {
  return {
    schema: 'pagebuilder/01',
    sections: [
      {
        id: 's1',
        type: 'section',
        data: {},
        blocks: [
          {
            id: 'c1',
            type: 'container',
            data: {
              blocks: [
                { id: 'col1', type: 'column', data: { width: '50', blocks: [] } },
                { id: 'col2', type: 'column', data: { width: '50', blocks: [] } },
              ],
            },
          },
        ],
      },
    ],
  } as unknown as LayoutPayload
}

// A fake runCommand that re-widths the container's columns so the payload actually
// changes (executeCommand only records history / selection on a real change).
function reWidth(before: LayoutPayload, widths: string[]): LayoutPayload {
  const clone = JSON.parse(JSON.stringify(before)) as any
  const container = clone.sections[0].blocks[0]
  container.data.blocks = widths.map((w, i) => ({
    id: container.data.blocks[i]?.id ?? `new-${i}`,
    type: 'column',
    data: { width: w, blocks: container.data.blocks[i]?.data?.blocks ?? [] },
  }))
  return clone
}

function makeStore(runCommand: ReturnType<typeof vi.fn>) {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = payload()
  store.client = { runCommand } as never
  return store
}

describe('store.applyColumnLayout (Phase 26.1)', () => {
  let runCommand: ReturnType<typeof vi.fn>
  let store: ReturnType<typeof useEditorStore>

  beforeEach(() => {
    runCommand = vi.fn(async (before: LayoutPayload, command: string, params: Record<string, unknown>) => {
      if (command === 'apply-column-layout') {
        return {
          payload: reWidth(before, params.widths as string[]),
          selection: { selected: params.container_id as string },
        }
      }
      return { payload: before, selection: { selected: null } }
    })
    store = makeStore(runCommand)
  })

  it("calls executeCommand('apply-column-layout', {container_id, widths})", async () => {
    await store.applyColumnLayout('c1', ['30', '70'])
    expect(runCommand).toHaveBeenCalledTimes(1)
    const [, command, params] = runCommand.mock.calls[0]
    expect(command).toBe('apply-column-layout')
    expect(params).toEqual({ container_id: 'c1', widths: ['30', '70'] })
  })

  it('records one undo entry for the whole re-proportion', async () => {
    expect(store.undoStack.length).toBe(0)
    await store.applyColumnLayout('c1', ['30', '70'])
    expect(store.undoStack.length).toBe(1)
  })

  it('keeps the container selected when it survives', async () => {
    await store.applyColumnLayout('c1', ['33.3333', '33.3333', '33.3333'])
    expect(store.selectedComponentId).toBe('c1')
  })

  it('clears the reconcile directive (structural change → full preview refresh)', async () => {
    store.reconcileComponentId = 'stale'
    await store.applyColumnLayout('c1', ['30', '70'])
    expect(store.reconcileComponentId).toBeNull()
  })
})
