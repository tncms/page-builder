import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import { useProvenanceGroupsStore, MAX_PENDING_GROUPS } from '../../browser/provenanceGroupsStore'
import type { EditorMetadata, LayoutPayload } from '../../types'

// PB-TREG-2 — the editor store's transport-only provenance behaviour: mint-at-insert
// adoption, opaque-group registration, save relay + clear-on-success, retain-on-failure,
// and saturation blocking. No client trust parsing; provenance authority stays server-side.

let seq = 0
function payload(): LayoutPayload {
  return { schema: 'x', sections: [{ id: 'sec', type: 'hero', data: { n: seq++ }, blocks: [] }] } as unknown as LayoutPayload
}

function insertResult(tokens: string[], rootIds: string[]) {
  return {
    payload: payload(),
    selection: { selected: rootIds[0] ?? null },
    artifacts: { provenanceAttestations: tokens, rootIds, expiresAt: 9_000, horizonAt: 30_000 },
  }
}

function scene(over: Record<string, unknown> = {}) {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = payload()
  store.versionToken = 'T0'
  store.metadata = { version: '1', sections: [{ type: 'hero' }], blocks: [], tokens: {} } as unknown as EditorMetadata
  const client = {
    insertRegisteredResource: vi.fn(async () => insertResult(['t1', 't2'], ['sec'])),
    saveDraft: vi.fn(async () => ({}) as never),
    publish: vi.fn(async () => ({}) as never),
    ...over,
  }
  ;(store as unknown as { client: unknown }).client = client
  return { store, client, groups: useProvenanceGroupsStore() }
}

beforeEach(() => (seq = 0))

describe('mint-at-insert adoption + group registration', () => {
  it('adopts the authoritative state and registers the opaque group', async () => {
    const { store, client, groups } = scene()
    const before = store.currentPayload
    const ok = await store.insertRegisteredResource('theme:section:hero')
    expect(ok).toBe(true)
    // The store sends only current state + expected token + RID + placement (no IDs/provenance).
    expect(client.insertRegisteredResource).toHaveBeenCalledWith(before, 'T0', 'theme:section:hero', {})
    // Group stored for the later save (opaque tokens, never parsed).
    expect(groups.relayTokens).toEqual(['t1', 't2'])
    expect(store.dirty).toBe(true)
  })

  it('blocks further materializations at the saturation bound (never a provenance-free retry)', async () => {
    const { store, groups } = scene()
    for (let i = 0; i < MAX_PENDING_GROUPS; i++) groups.register({ tokens: [`x${i}`], rootIds: [`r${i}`], expiresAt: 9_000, horizonAt: 30_000 })
    const ok = await store.insertRegisteredResource('theme:section:hero')
    expect(ok).toBe(false)
    expect(store.commandError).toBe('pb_provenance_limit')
  })
})

describe('save relay + lifecycle', () => {
  it('relays pending groups on save and clears them only after success', async () => {
    const { store, client, groups } = scene()
    await store.insertRegisteredResource('theme:section:hero')
    expect(groups.count).toBe(1)

    const saved = await store.saveDraft()
    expect(saved).toBe(true)
    expect(client.saveDraft).toHaveBeenCalledWith(store.currentPayload, 'T0', ['t1', 't2'])
    // Persisted → pending transport cleared.
    expect(groups.relayTokens).toEqual([])
  })

  it('retains pending groups + editor payload after a failed save', async () => {
    const { store, groups } = scene({ saveDraft: vi.fn(async () => { throw new Error('boom') }) })
    await store.insertRegisteredResource('theme:section:hero')

    const saved = await store.saveDraft()
    expect(saved).toBe(false)
    // Groups retained for explicit recovery; nothing silently dropped.
    expect(groups.relayTokens).toEqual(['t1', 't2'])
  })

  it('a legacy save with no pending groups relays nothing (arity-compatible)', async () => {
    const { store, client } = scene()
    const saved = await store.saveDraft()
    expect(saved).toBe(true)
    expect(client.saveDraft).toHaveBeenCalledWith(store.currentPayload, 'T0')
  })
})
