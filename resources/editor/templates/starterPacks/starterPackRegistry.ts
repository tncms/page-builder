// Phase 26.28 — starter pack registry (deterministic, validation-first, immutable identity).
//
// Holds starter pack METADATA only — it references templates, it never stores or owns them
// (removing a pack never touches a template). Registration is validation-first (invalid /
// incompatible packs throw `StarterPackError` before anything is stored) and identity-strict
// (duplicate ids are rejected). Reads return clones, so stored metadata is immutable from outside.
// `freeze()` makes the set immutable after boot.

import { checkCompatibilityRequirements, type BuilderEnvironment, type CompatibilityIssue } from '../compatibility'
import { STARTER_PACK_SOURCE, StarterPackError, type TemplateStarterPack } from './starterPackTypes'
import { validateStarterPack, type ValidateStarterPackOptions } from './starterPackValidation'

function clone(pack: TemplateStarterPack): TemplateStarterPack {
  return JSON.parse(JSON.stringify(pack)) as TemplateStarterPack
}

export interface StarterPackRegistrationResult {
  ok: boolean
  packId: string
  /** Non-blocking compatibility warnings (Phase 26.27). */
  warnings: CompatibilityIssue[]
}

export type RegisterStarterPackOptions = Omit<ValidateStarterPackOptions, 'knownPackIds'>

export class TemplateStarterPackRegistry {
  private readonly packs = new Map<string, TemplateStarterPack>()
  private frozen = false

  /** Register a pack. Throws `StarterPackError` on invalid/incompatible/duplicate. */
  register(pack: TemplateStarterPack, options: RegisterStarterPackOptions = {}): StarterPackRegistrationResult {
    if (this.frozen) {
      throw new StarterPackError(`Starter pack registry is frozen; cannot register "${pack?.id ?? ''}".`, [
        { code: 'registry-frozen', message: 'The registry is frozen after boot.' },
      ])
    }
    const issues = validateStarterPack(pack, { ...options, knownPackIds: new Set(this.packs.keys()) })
    if (issues.length > 0) {
      throw new StarterPackError(`Starter pack "${pack?.id ?? ''}" is invalid.`, issues)
    }

    const stored = clone(pack)
    stored.source = stored.source ?? STARTER_PACK_SOURCE.builtin
    this.packs.set(stored.id, stored)

    const req = checkCompatibilityRequirements(pack.compatibility, options.environment)
    return { ok: true, packId: stored.id, warnings: req.warnings }
  }

  /** One pack by id (immutable clone), or undefined. */
  find(id: string): TemplateStarterPack | undefined {
    const pack = this.packs.get(id)
    return pack ? clone(pack) : undefined
  }

  /** Every registered pack (immutable clones), in registration order. */
  all(): TemplateStarterPack[] {
    return [...this.packs.values()].map(clone)
  }

  has(id: string): boolean {
    return this.packs.has(id)
  }

  /** Remove a pack. Referenced templates are independent and unaffected. */
  remove(id: string): boolean {
    return this.packs.delete(id)
  }

  freeze(): void {
    this.frozen = true
  }

  get isFrozen(): boolean {
    return this.frozen
  }
}

/** The default starter pack registry. */
export const templateStarterPackRegistry = new TemplateStarterPackRegistry()
