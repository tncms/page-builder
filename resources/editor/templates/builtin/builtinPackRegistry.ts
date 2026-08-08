// Phase 26.25 — built-in pack registry (deterministic, validation-first, duplicate-safe).
//
// Registers packs into the Phase 26.24 `BuiltinTemplateDefinitionSource` (the ONE built-in
// definition store — no new resolver, no duplicate storage). Order:
//   1. VALIDATE (throws `BuiltinPackError` on any issue → fails at load, never at apply).
//   2. Register every definition into the builtin source.
//   3. Record the manifest.
//
// Re-registering the same pack id is a safe no-op (idempotent boot / HMR). The registry can be
// frozen after boot so the built-in set is immutable at runtime.

import { builtinTemplateDefinitionSource } from '../source'
import type { TemplateDefinition } from '../contracts'
import { validatePack, type ValidatePackOptions } from './packValidation'
import { BuiltinPackError, type BuiltinTemplatePack, type BuiltinTemplatePackManifest } from './builtinPackTypes'

/** The subset of the builtin definition source the registry writes to. */
export interface BuiltinDefinitionSink {
  register(definition: TemplateDefinition): TemplateDefinition
}

export interface PackRegistrationResult {
  ok: boolean
  packId: string
  skipped: boolean
  templateIds: string[]
}

export class BuiltinTemplatePackRegistry {
  private readonly manifests = new Map<string, BuiltinTemplatePackManifest>()
  private frozen = false

  constructor(private readonly sink: BuiltinDefinitionSink = builtinTemplateDefinitionSource) {}

  /** Register a pack. Idempotent by pack id; throws `BuiltinPackError` when validation fails. */
  register(pack: BuiltinTemplatePack, options: ValidatePackOptions = {}): PackRegistrationResult {
    const id = pack?.manifest?.id ?? ''
    if (this.manifests.has(id)) {
      return { ok: true, packId: id, skipped: true, templateIds: pack.manifest.templates }
    }
    if (this.frozen) {
      throw new BuiltinPackError(`Built-in pack registry is frozen; cannot register "${id}".`, [
        { code: 'registry-frozen', message: 'The registry is frozen after boot.' },
      ])
    }

    const issues = validatePack(pack, { ...options, knownPackIds: new Set(this.manifests.keys()) })
    if (issues.length > 0) {
      throw new BuiltinPackError(`Built-in pack "${id}" is invalid.`, issues)
    }

    for (const definition of pack.definitions) this.sink.register(definition)
    this.manifests.set(id, { ...pack.manifest })

    return { ok: true, packId: id, skipped: false, templateIds: [...pack.manifest.templates] }
  }

  /** Registered pack manifests, in registration order (immutable copies). */
  packs(): BuiltinTemplatePackManifest[] {
    return [...this.manifests.values()].map((m) => ({ ...m }))
  }

  has(packId: string): boolean {
    return this.manifests.has(packId)
  }

  /** Make the built-in set immutable (called after boot). */
  freeze(): void {
    this.frozen = true
  }

  get isFrozen(): boolean {
    return this.frozen
  }
}

/** The default registry, writing into the shared builtin definition source. */
export const builtinTemplatePackRegistry = new BuiltinTemplatePackRegistry()
