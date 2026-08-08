// Phase 26.25 — boot registration for the bundled built-in packs.
//
// Registers the first-party packs into the definition registry (→ resolvable + applyable) and,
// by default, publishes their metadata to the Phase 26.21 built-in library (→ discoverable in the
// Picker). Called once at editor boot. NOT a top-level side effect — importing this module
// registers nothing, so tests and other consumers stay deterministic.

import { builtinTemplatePackRegistry, type BuiltinTemplatePackRegistry } from './builtinPackRegistry'
import type { ValidatePackOptions } from './packValidation'
import type { BuiltinTemplatePack } from './builtinPackTypes'
import { builtinTemplateLibrary, toLibraryItem, TEMPLATE_SOURCE } from '../library'
import { companyPack } from './packs/company/companyPack'

/** The packs shipped with the editor. Add first-party packs here. */
export const BUILTIN_TEMPLATE_PACKS: readonly BuiltinTemplatePack[] = [companyPack]

export interface RegisterBuiltinPacksOptions {
  registry?: BuiltinTemplatePackRegistry
  /** Passed to validation (e.g. editor metadata for strict type checks). */
  validate?: ValidatePackOptions
  /** Publish pack templates to the built-in library for discovery (default true). */
  publishToLibrary?: boolean
  /** Freeze the registry after registering (immutable after boot; default false). */
  freeze?: boolean
}

/** Register every bundled pack. Idempotent by pack id. Throws `BuiltinPackError` on an invalid pack. */
export function registerBuiltinPacks(options: RegisterBuiltinPacksOptions = {}): void {
  const registry = options.registry ?? builtinTemplatePackRegistry
  const publish = options.publishToLibrary !== false

  for (const pack of BUILTIN_TEMPLATE_PACKS) {
    const result = registry.register(pack, options.validate)
    if (publish && !result.skipped) {
      for (const definition of pack.definitions) {
        builtinTemplateLibrary.register(toLibraryItem(definition, TEMPLATE_SOURCE.builtin))
      }
    }
  }

  if (options.freeze) registry.freeze()
}
