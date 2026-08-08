// Phase 26.28 — deterministic registration of the bundled starter packs.
//
// Registers the built-in/local starter packs into the registry. Idempotent by pack id (safe on
// boot / HMR). NOT a top-level side effect — importing this module registers nothing. Reference
// validation is best-effort: pass `knownTemplateIds` to enforce that referenced templates exist.

import { templateStarterPackRegistry, type TemplateStarterPackRegistry } from './starterPackRegistry'
import type { RegisterStarterPackOptions } from './starterPackRegistry'
import type { TemplateStarterPack } from './starterPackTypes'
import { companyStarterPack } from './packs/companyStarterPack'
import { agencyStarterPack } from './packs/agencyStarterPack'

/** The starter packs shipped with the editor. */
export const BUILTIN_STARTER_PACKS: readonly TemplateStarterPack[] = [companyStarterPack, agencyStarterPack]

export interface RegisterStarterPacksOptions extends RegisterStarterPackOptions {
  registry?: TemplateStarterPackRegistry
  /** Freeze the registry after registering (immutable after boot; default false). */
  freeze?: boolean
}

/** Register every bundled starter pack. Idempotent by pack id. Throws on an invalid/incompatible pack. */
export function registerStarterPacks(options: RegisterStarterPacksOptions = {}): void {
  const { registry = templateStarterPackRegistry, freeze, ...registerOptions } = options
  for (const pack of BUILTIN_STARTER_PACKS) {
    if (!registry.has(pack.id)) registry.register(pack, registerOptions)
  }
  if (freeze) registry.freeze()
}
