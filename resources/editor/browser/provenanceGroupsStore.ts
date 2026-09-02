// PB-TREG-2 — the editor's pending-group registry: bounded, opaque TRANSPORT state only.
//
// It holds the opaque attestation groups returned by the mint-at-insert endpoint until a
// save persists them. It is NOT a provenance authority: it never parses token bodies,
// derives provenance, creates chunks, predicts IDs, or makes a trust decision. Persisted
// provenance is owned entirely by the server; this registry only decides WHICH opaque
// groups to relay/renew/rebind, keyed by the server-returned transport identity and the
// group's own root ids (used solely for lifecycle tracking).
import { defineStore } from 'pinia'

export interface PendingGroup {
  /** Client transport id (never the signed group id). */
  id: string
  /** The opaque signed chunk tokens — relayed verbatim, never parsed. */
  tokens: string[]
  /** The server-materialized root ids this group covers (lifecycle tracking only). */
  rootIds: string[]
  /** Safe (non-secret) operational expiry, provided by the server (not parsed from tokens). */
  expiresAt: number
  /** Safe absolute renewal horizon, provided by the server. */
  horizonAt: number
}

/** Client-side mirror of the server per-save transport bound (saturation guard). */
export const MAX_PENDING_GROUPS = 64

export const useProvenanceGroupsStore = defineStore('pbProvenanceGroups', {
  state: () => ({
    groups: [] as PendingGroup[],
    seq: 0,
  }),

  getters: {
    /** Every opaque token across pending groups, for the saveDraft relay. */
    relayTokens(state): string[] {
      return state.groups.flatMap((g) => g.tokens)
    },
    count(state): number {
      return state.groups.length
    },
    /** At the saturation bound: block further materializations until a successful save. */
    atLimit(state): boolean {
      return state.groups.length >= MAX_PENDING_GROUPS
    },
  },

  actions: {
    /**
     * Register a freshly-materialized group. Returns the transport id, or null when the
     * saturation bound is reached (the caller must require a successful save first) or the
     * group is empty. Editor duplication never calls this, so a duplicate is never covered.
     */
    register(group: Omit<PendingGroup, 'id'>): string | null {
      if (this.atLimit || group.tokens.length === 0 || group.rootIds.length === 0) {
        return null
      }
      const id = `pg-${++this.seq}`
      this.groups.push({ id, ...group })
      return id
    },

    /**
     * Reconcile pending groups against the current payload node ids after ordinary edits:
     *   - a group whose EVERY root was deleted is dropped (no covered content remains);
     *   - a group whose root set shrank to a strict subset needs rebind (returned with the
     *     surviving root ids); ordinary data edits that keep the roots retain the group.
     *
     * @returns groups requiring an authenticated rebind, with their surviving root ids.
     */
    reconcile(presentIds: Set<string>): Array<{ id: string; rootIds: string[] }> {
      const rebind: Array<{ id: string; rootIds: string[] }> = []
      this.groups = this.groups.filter((g) => {
        const remaining = g.rootIds.filter((id) => presentIds.has(id))
        if (remaining.length === 0) return false
        if (remaining.length < g.rootIds.length) rebind.push({ id: g.id, rootIds: remaining })
        return true
      })
      return rebind
    },

    /** Groups whose operational TTL has passed but are still within the absolute horizon. */
    renewable(now: number): PendingGroup[] {
      return this.groups.filter((g) => now >= g.expiresAt && now < g.horizonAt)
    },

    /** Atomically replace a group's opaque tokens after a successful renew or rebind. */
    replace(id: string, group: Omit<PendingGroup, 'id'>): void {
      const i = this.groups.findIndex((g) => g.id === id)
      if (i >= 0) this.groups[i] = { id, ...group }
    },

    /** Drop a group (explicit discard, or every covered root deleted). */
    discard(id: string): void {
      this.groups = this.groups.filter((g) => g.id !== id)
    },

    /** Clear pending transport groups ONLY after a confirmed successful save persisted them. */
    clearAfterSuccessfulSave(): void {
      this.groups = []
    },
  },
})
