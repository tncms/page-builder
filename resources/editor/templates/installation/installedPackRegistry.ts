// Phase 26.30 — installed pack ledger (references only, no definition storage).
//
// The record of which packs a site has adopted. It stores availability records (ids + references)
// — NEVER template definitions — so it is not a second template registry and duplicates no storage.
// Reads return immutable clones. `availableTemplateIds()` is the union of installed references (the
// "installed template source" surface, references only).

import type { InstalledPack } from './installationTypes'

function clone(pack: InstalledPack): InstalledPack {
  return { ...pack, templateIds: [...pack.templateIds] }
}

export class InstalledPackRegistry {
  private readonly installed = new Map<string, InstalledPack>()

  /** Record an installation (by installation id). Returns a clone. */
  register(pack: InstalledPack): InstalledPack {
    const stored = clone(pack)
    this.installed.set(stored.id, stored)
    return clone(stored)
  }

  has(id: string): boolean {
    return this.installed.has(id)
  }

  find(id: string): InstalledPack | undefined {
    const pack = this.installed.get(id)
    return pack ? clone(pack) : undefined
  }

  /** Every installed pack (immutable clones), in installation order. */
  all(): InstalledPack[] {
    return [...this.installed.values()].map(clone)
  }

  /** Remove an installation record. Referenced templates are untouched (references only). */
  remove(id: string): boolean {
    return this.installed.delete(id)
  }

  /** The union of all installed packs' referenced template ids (deduped, references only). */
  availableTemplateIds(): string[] {
    const ids = new Set<string>()
    for (const pack of this.installed.values()) for (const id of pack.templateIds) ids.add(id)
    return [...ids]
  }
}

/** The default installed pack ledger. */
export const installedPackRegistry = new InstalledPackRegistry()
