import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useProvenanceGroupsStore, MAX_PENDING_GROUPS } from '../provenanceGroupsStore'

// PB-TREG-2 — the pending-group registry is opaque TRANSPORT state only: it relays,
// reconciles, renews, rebinds and clears groups by their root ids, never parsing tokens.

function group(over: Partial<{ tokens: string[]; rootIds: string[]; expiresAt: number; horizonAt: number }> = {}) {
  return {
    tokens: over.tokens ?? ['t1', 't2'],
    rootIds: over.rootIds ?? ['pb-a', 'pb-b'],
    expiresAt: over.expiresAt ?? 4_600,
    horizonAt: over.horizonAt ?? 30_000,
  }
}

beforeEach(() => setActivePinia(createPinia()))

describe('register + relay', () => {
  it('registers a group and relays every opaque token', () => {
    const store = useProvenanceGroupsStore()
    const id = store.register(group())
    expect(id).toBeTruthy()
    expect(store.count).toBe(1)
    expect(store.relayTokens).toEqual(['t1', 't2'])
  })

  it('refuses empty groups and blocks at the saturation bound', () => {
    const store = useProvenanceGroupsStore()
    expect(store.register(group({ tokens: [] }))).toBeNull()
    expect(store.register(group({ rootIds: [] }))).toBeNull()
    for (let i = 0; i < MAX_PENDING_GROUPS; i++) store.register(group({ rootIds: [`r${i}`] }))
    expect(store.atLimit).toBe(true)
    expect(store.register(group({ rootIds: ['overflow'] }))).toBeNull()
  })
})

describe('reconcile after edits', () => {
  it('retains a group across ordinary edits that keep its roots', () => {
    const store = useProvenanceGroupsStore()
    store.register(group({ rootIds: ['pb-a', 'pb-b'] }))
    const rebind = store.reconcile(new Set(['pb-a', 'pb-b', 'pb-unrelated']))
    expect(rebind).toEqual([])
    expect(store.count).toBe(1)
  })

  it('drops a group when every covered root is deleted', () => {
    const store = useProvenanceGroupsStore()
    store.register(group({ rootIds: ['pb-a', 'pb-b'] }))
    const rebind = store.reconcile(new Set(['pb-unrelated']))
    expect(rebind).toEqual([])
    expect(store.count).toBe(0)
  })

  it('flags rebind when only a strict subset of roots remains', () => {
    const store = useProvenanceGroupsStore()
    const id = store.register(group({ rootIds: ['pb-a', 'pb-b', 'pb-c'] }))
    const rebind = store.reconcile(new Set(['pb-a', 'pb-c']))
    expect(rebind).toEqual([{ id, rootIds: ['pb-a', 'pb-c'] }])
    expect(store.count).toBe(1) // retained pending rebind, not dropped
  })
})

describe('renewal / replace / discard / clear', () => {
  it('lists renewable groups (expired but within horizon) only', () => {
    const store = useProvenanceGroupsStore()
    store.register(group({ rootIds: ['pb-fresh'], expiresAt: 5_000, horizonAt: 30_000 }))
    store.register(group({ rootIds: ['pb-stale'], expiresAt: 4_000, horizonAt: 30_000 }))
    store.register(group({ rootIds: ['pb-dead'], expiresAt: 4_000, horizonAt: 4_500 }))
    const renewable = store.renewable(4_600).map((g) => g.rootIds[0])
    expect(renewable).toEqual(['pb-stale']) // fresh not yet expired; dead past horizon
  })

  it('atomically replaces a group after renew/rebind and can discard it', () => {
    const store = useProvenanceGroupsStore()
    const id = store.register(group())!
    store.replace(id, group({ tokens: ['new1'], rootIds: ['pb-a'], expiresAt: 9_000, horizonAt: 30_000 }))
    expect(store.relayTokens).toEqual(['new1'])
    store.discard(id)
    expect(store.count).toBe(0)
  })

  it('clears pending groups only after a successful save', () => {
    const store = useProvenanceGroupsStore()
    store.register(group())
    store.register(group({ rootIds: ['pb-x'] }))
    expect(store.count).toBe(2)
    store.clearAfterSuccessfulSave()
    expect(store.count).toBe(0)
    expect(store.relayTokens).toEqual([])
  })
})
