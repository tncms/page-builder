import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import type { DragSubject, DropResolution } from '../../drag'
import type { LayoutPayload } from '../../types'

// A small page: sec1 holds blk1 + blk2; sec2 is empty.
function payload(): LayoutPayload {
  return {
    version: 1,
    sections: [
      {
        id: 'sec1',
        type: 'section',
        data: {},
        blocks: [
          { id: 'blk1', type: 'text', data: {} },
          { id: 'blk2', type: 'text', data: {} },
        ],
      },
      { id: 'sec2', type: 'section', data: {}, blocks: [] },
    ],
  } as unknown as LayoutPayload
}

function subject(over: Partial<DragSubject> & Pick<DragSubject, 'id' | 'kind'>): DragSubject {
  return { type: 'text', label: 'Text', icon: null, parentId: 'sec1', index: 0, ...over }
}

function resolution(over: Partial<DropResolution> = {}): DropResolution {
  return {
    valid: true,
    reason: null,
    edge: 'after',
    targetId: 'blk2',
    referenceId: 'blk2',
    parentId: 'sec1',
    markerRect: null,
    ...over,
  }
}

describe('editor store — drag actions (Phase 14)', () => {
  let store: ReturnType<typeof useEditorStore>
  let runCommand: ReturnType<typeof vi.fn>

  beforeEach(() => {
    // happy-dom's structuredClone rejects Vue reactive proxies; the browser
    // clones them fine. Swap in a JSON clone so history snapshots work here.
    // Simulate the browser: structuredClone throws DataCloneError on reactive
    // proxies. The clone path must NOT depend on it (Phase 14.2 hotfix).
    vi.stubGlobal('structuredClone', () => {
      throw new Error("Failed to execute 'structuredClone': could not be cloned.")
    })
    setActivePinia(createPinia())
    store = useEditorStore()
    store.currentPayload = payload()
    runCommand = vi.fn(async (_before, _command, _params) => ({
      // Return a distinctly different payload so "changed after drop" is observable.
      payload: { version: 2, sections: [] } as unknown as LayoutPayload,
      selection: { selected: null },
    }))
    // Inject a minimal fake ApiClient.
    ;(store as unknown as { client: unknown }).client = { runCommand }
  })

  it('startDrag activates the drag, selects the subject, and seeds the ghost', () => {
    store.startDrag(subject({ id: 'blk1', kind: 'block' }), 40, 60)
    expect(store.dragActive).toBe(true)
    expect(store.selectedComponentId).toBe('blk1')
    expect(store.dragGhost).toMatchObject({ label: 'Text', type: 'text', x: 40, y: 60 })
  })

  it('updateDrag mirrors the resolution WITHOUT mutating the payload', () => {
    const before = JSON.stringify(store.currentPayload)
    store.startDrag(subject({ id: 'blk1', kind: 'block' }), 0, 0)
    store.updateDrag(120, 200, resolution({ edge: 'before', targetId: 'blk2' }))
    expect(store.dragTarget).toBe('blk2')
    expect(store.dragEdge).toBe('before')
    expect(JSON.stringify(store.currentPayload)).toBe(before) // payload untouched during drag
    expect(store.dragGhost).toMatchObject({ x: 120, y: 200 })
  })

  it('finishDrag fires move-component exactly once and changes the payload', async () => {
    const before = JSON.stringify(store.currentPayload)
    store.startDrag(subject({ id: 'blk1', kind: 'block', parentId: 'sec1', index: 0 }), 0, 0)
    await store.finishDrag(resolution({ edge: 'after', referenceId: 'blk2', parentId: 'sec1' }))

    expect(runCommand).toHaveBeenCalledTimes(1)
    const [, command, params] = runCommand.mock.calls[0]
    expect(command).toBe('move-component')
    // blk2 is index 1; "after" → 2, minus 1 for same-parent removal of blk1 → 1.
    expect(params).toMatchObject({ component_id: 'blk1', target_parent_id: 'sec1', index: 1 })
    expect(JSON.stringify(store.currentPayload)).not.toBe(before) // changed after drop
    expect(store.dragActive).toBe(false)
  })

  it('keeps the moved component selected after the drop', async () => {
    store.startDrag(subject({ id: 'blk1', kind: 'block' }), 0, 0)
    await store.finishDrag(resolution())
    expect(store.selectedComponentId).toBe('blk1')
  })

  it('computes an append index for an inside drop', async () => {
    store.startDrag(subject({ id: 'blk1', kind: 'block', parentId: 'sec1', index: 0 }), 0, 0)
    await store.finishDrag(resolution({ edge: 'inside', parentId: 'sec2', referenceId: 'sec2' }))
    const [, , params] = runCommand.mock.calls[0]
    // sec2 has 0 children and blk1 comes from a different parent → append at 0.
    expect(params).toMatchObject({ target_parent_id: 'sec2', index: 0 })
  })

  it('ignores an invalid drop (no command, drag cleared)', async () => {
    store.startDrag(subject({ id: 'blk1', kind: 'block' }), 0, 0)
    await store.finishDrag(resolution({ valid: false, reason: 'nope' }))
    expect(runCommand).not.toHaveBeenCalled()
    expect(store.dragActive).toBe(false)
  })

  it('ignores a null drop (cancelled gesture)', async () => {
    store.startDrag(subject({ id: 'blk1', kind: 'block' }), 0, 0)
    await store.finishDrag(null)
    expect(runCommand).not.toHaveBeenCalled()
    expect(store.dragActive).toBe(false)
  })

  it('cancelDrag clears all drag state without a command', () => {
    store.startDrag(subject({ id: 'blk1', kind: 'block' }), 0, 0)
    store.cancelDrag()
    expect(runCommand).not.toHaveBeenCalled()
    expect(store.dragActive).toBe(false)
    expect(store.dragState).toBeNull()
    expect(store.dragGhost).toBeNull()
  })
})
