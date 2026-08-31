import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore, AUTOSAVE_DEBOUNCE_MS } from '../editor'
import type { EditorMetadata, LayoutPayload } from '../../types'

// PB-PROD-2 — Save Safety. Autosave (debounced, draft-only), save-state model,
// stale/race protection, failure/retry, and unsaved-change (beforeunload) risk.
// All against the ONE canonical store the Structure + Live editors share.

let seq = 0
function changedPayload(): LayoutPayload {
  // A fresh object each call → a new `currentPayload` reference (the generation).
  return { schema: 'x', sections: [{ id: 'sec', type: 'hero', data: { n: seq++ }, blocks: [] }] } as unknown as LayoutPayload
}

function scene() {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = changedPayload()
  store.metadata = { version: '1', sections: [{ type: 'hero' }], blocks: [], tokens: {} } as unknown as EditorMetadata
  const client = {
    runCommand: vi.fn(async () => ({ payload: changedPayload(), selection: { selected: null } })),
    saveDraft: vi.fn(async () => ({}) as never),
    publish: vi.fn(async () => ({}) as never),
  }
  ;(store as unknown as { client: unknown }).client = client
  return { store, client }
}

// Flush pending microtasks under fake timers (no real setTimeout).
const flush = () => vi.advanceTimersByTimeAsync(0)

beforeEach(() => {
  vi.useFakeTimers()
  seq = 0
})
afterEach(() => {
  useEditorStore().teardownUnloadGuard?.()
  vi.useRealTimers()
})

describe('save-state model + dirty lifecycle', () => {
  it('a hydrated document is clean and schedules no autosave', () => {
    const { store, client } = scene()
    store.applyContext({
      owner: {}, layout: {}, metadata: store.metadata, permissions: {},
    } as never)
    expect(store.saveState).toBe('clean')
    expect(store.hasUnsavedRisk).toBe(false)
    vi.advanceTimersByTime(AUTOSAVE_DEBOUNCE_MS * 2)
    expect(client.saveDraft).not.toHaveBeenCalled()
  })

  it('an edit marks dirty + pending and drives the save-state model', async () => {
    const { store } = scene()
    await store.executeCommand('noop')
    expect(store.dirty).toBe(true)
    expect(store.saveState).toBe('pending')
    expect(store.hasUnsavedRisk).toBe(true)
  })
})

describe('autosave contract', () => {
  it('debounces a dirty edit and saves the draft once after the quiet period', async () => {
    const { store, client } = scene()
    await store.executeCommand('noop')
    expect(client.saveDraft).not.toHaveBeenCalled() // not yet
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS)
    expect(client.saveDraft).toHaveBeenCalledTimes(1)
    expect(store.dirty).toBe(false)
    expect(store.saveState).toBe('saved')
  })

  it('coalesces rapid edits into a single save (no request storm)', async () => {
    const { store, client } = scene()
    await store.executeCommand('a')
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS / 3)
    await store.executeCommand('b') // re-arms
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS / 3)
    await store.executeCommand('c') // re-arms again
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS)
    expect(client.saveDraft).toHaveBeenCalledTimes(1)
  })

  it('does nothing when the document is clean', async () => {
    const { store, client } = scene()
    await store.runAutosave()
    expect(client.saveDraft).not.toHaveBeenCalled()
  })

  it('never publishes', async () => {
    const { store, client } = scene()
    await store.executeCommand('noop')
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS)
    expect(client.publish).not.toHaveBeenCalled()
  })

  it('saves the LATEST payload', async () => {
    const { store, client } = scene()
    await store.executeCommand('a')
    await store.executeCommand('b')
    const latest = store.currentPayload
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS)
    expect(client.saveDraft).toHaveBeenCalledWith(latest, null)
  })
})

describe('race / stale-response protection', () => {
  it('an edit during an in-flight save stays dirty after the stale success', async () => {
    const { store, client } = scene()
    let release!: () => void
    const gate = new Promise<void>((r) => { release = r })
    client.saveDraft.mockImplementationOnce(async () => { await gate; return {} as never })

    store.dirty = true
    const saving = store.saveDraft() // captures the current generation
    await flush() // let persistDraft reach the gated client.saveDraft
    await store.executeCommand('newer') // new currentPayload reference while save in flight

    release()
    await saving
    await flush()
    // Stale success must NOT clean the newer edit.
    expect(store.dirty).toBe(true)
    expect(store.hasUnsavedRisk).toBe(true)
  })
})

describe('failure + retry', () => {
  it('a failed autosave stays dirty + failed and keeps unsaved risk; retry recovers', async () => {
    const { store, client } = scene()
    client.saveDraft.mockRejectedValueOnce(new Error('network boom'))
    await store.executeCommand('noop')
    // Fire the armed autosave deterministically (await the write, not the timer's
    // fire-and-forget chain) so the failure outcome is fully settled.
    await store.runAutosave()

    expect(store.dirty).toBe(true)
    expect(store.saveFailed).toBe(true)
    expect(store.saveState).toBe('failed')
    expect(store.hasUnsavedRisk).toBe(true)

    // Manual retry succeeds.
    expect(await store.saveDraft()).toBe(true)
    expect(store.dirty).toBe(false)
    expect(store.saveFailed).toBe(false)
  })
})

describe('manual save + publish interaction', () => {
  it('a manual save during a pending autosave supersedes it (no double write)', async () => {
    const { store, client } = scene()
    await store.executeCommand('noop') // arms autosave (pending)
    expect(store.savePending).toBe(true)

    await store.saveDraft() // manual — must cancel the armed autosave
    expect(store.savePending).toBe(false)
    expect(client.saveDraft).toHaveBeenCalledTimes(1)

    // The cancelled autosave must NOT fire a second write.
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS * 2)
    expect(client.saveDraft).toHaveBeenCalledTimes(1)
  })

  it('Publish persists the CURRENT revision (pending edit) before publishing — no stale draft', async () => {
    const { store, client } = scene()
    const order: string[] = []
    client.saveDraft.mockImplementation(async () => { order.push('save'); return {} as never })
    client.publish.mockImplementation(async () => { order.push('publish'); return {} as never })

    await store.executeCommand('edit') // arms autosave; NOT yet saved
    const latest = store.currentPayload

    expect(await store.publish()).toBe(true)
    expect(order).toEqual(['save', 'publish']) // save-then-publish, current revision
    expect(client.saveDraft).toHaveBeenLastCalledWith(latest, null)
    // Publish consumed the pending edit; no leftover autosave fires afterwards.
    expect(store.savePending).toBe(false)
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS * 2)
    expect(client.saveDraft).toHaveBeenCalledTimes(1)
  })

  it('autosave never mutates the published payload (draft endpoint only)', async () => {
    const { store, client } = scene()
    await store.executeCommand('noop')
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS)
    expect(client.saveDraft).toHaveBeenCalledTimes(1)
    expect(client.publish).not.toHaveBeenCalled()
  })
})

describe('data fidelity — autosave uses the canonical serializer', () => {
  it('sends nested components + responsive envelopes verbatim', async () => {
    const { store, client } = scene()
    // A nested, responsive, tokened payload (PB-PROD-1 envelope preserved).
    const rich = {
      schema: 'x',
      sections: [{
        id: 'sec', type: 'hero',
        data: { spacing: { padding: { desktop: { top: 40 }, tablet: { top: 20 }, mobile: { top: 10 } } }, background: 'color.primary' },
        blocks: [{ id: 'b1', type: 'heading', data: { text: 'Hi' } }],
      }],
    } as unknown as LayoutPayload
    store.currentPayload = rich
    store.dirty = true

    await store.runAutosave()

    expect(client.saveDraft).toHaveBeenCalledWith(rich, null)
    const sent = client.saveDraft.mock.calls[0][0] as unknown as typeof rich
    expect(sent.sections[0].data.spacing.padding.mobile.top).toBe(10) // envelope intact
    expect(sent.sections[0].blocks[0].id).toBe('b1') // nested id/order intact
  })
})

describe('undo/redo + save state', () => {
  it('an edit after a clean save re-arms autosave; undo stays protected', async () => {
    const { store, client } = scene()
    await store.executeCommand('a')
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS)
    expect(store.dirty).toBe(false)
    expect(store.saveState).toBe('saved')

    store.undo() // client-side revert → dirty + risk again
    expect(store.dirty).toBe(true)
    expect(store.hasUnsavedRisk).toBe(true)
    expect(store.savePending).toBe(true)
  })
})

describe('exit guard + timer hygiene', () => {
  it('exit() confirms when there is unsaved risk and proceeds when clean', () => {
    const { store } = scene()
    ;(store as unknown as { boot: unknown }).boot = { exitUrl: '/back' }
    const assign = vi.spyOn(window.location, 'assign').mockImplementation(() => {})
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false)

    store.dirty = true
    store.exit() // risky + declined → no navigation
    expect(assign).not.toHaveBeenCalled()

    store.dirty = false
    store.exit() // clean → navigates without a prompt
    expect(assign).toHaveBeenCalledWith('/back')

    confirm.mockRestore()
    assign.mockRestore()
  })

  it('cancelAutosave clears the armed timer (no leak / no late write)', async () => {
    const { store, client } = scene()
    await store.executeCommand('noop')
    expect(store.autosaveTimer).not.toBeNull()
    store.cancelAutosave()
    expect(store.autosaveTimer).toBeNull()
    expect(store.savePending).toBe(false)
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS * 2)
    expect(client.saveDraft).not.toHaveBeenCalled()
  })
})

describe('beforeunload protection', () => {
  it('warns only while there is unsaved risk and cleans up on teardown', () => {
    const { store } = scene()
    const add = vi.spyOn(window, 'addEventListener')
    const remove = vi.spyOn(window, 'removeEventListener')

    store.installUnloadGuard()
    const handler = add.mock.calls.find((c) => c[0] === 'beforeunload')?.[1] as (e: BeforeUnloadEvent) => void
    expect(handler).toBeTypeOf('function')

    // Clean → no prevention.
    const clean = { preventDefault: vi.fn(), returnValue: undefined } as unknown as BeforeUnloadEvent
    handler(clean)
    expect(clean.preventDefault).not.toHaveBeenCalled()

    // Dirty → prevented.
    store.dirty = true
    const risky = { preventDefault: vi.fn(), returnValue: undefined } as unknown as BeforeUnloadEvent
    handler(risky)
    expect(risky.preventDefault).toHaveBeenCalled()

    store.teardownUnloadGuard()
    expect(remove).toHaveBeenCalledWith('beforeunload', handler)
  })
})
