// Phase 26.24 — in-memory definition source (base for builtin + imported).
//
// Holds full `TemplateDefinition`s in memory, keyed by templateId, for ONE source. Reads return
// deep clones (immutable — callers can never mutate the stored definition). Empty by default;
// definitions are seeded via `register`. This is the built-in / imported storage seam: no
// marketplace, no downloads, no persistence.

import type { TemplateDefinition } from '../contracts'
import { TemplateRuntimeError } from '../runtime'
import type { TemplateDefinitionSource } from './TemplateDefinitionSource'

function clone(def: TemplateDefinition): TemplateDefinition {
  return JSON.parse(JSON.stringify(def)) as TemplateDefinition
}

export class StaticTemplateDefinitionSource implements TemplateDefinitionSource {
  private readonly definitions = new Map<string, TemplateDefinition>()

  constructor(public readonly source: string) {}

  getDefinition(templateId: string): TemplateDefinition | null {
    const def = this.definitions.get(templateId)
    return def ? clone(def) : null
  }

  /** Seed a definition. Rejects a duplicate id (mirrors the runtime provider's guarantee). */
  register(def: TemplateDefinition): TemplateDefinition {
    if (this.definitions.has(def.id)) {
      throw new TemplateRuntimeError('duplicate_registration', `Template "${def.id}" is already registered.`, {
        id: def.id,
        source: this.source,
      })
    }
    const stored = clone(def)
    this.definitions.set(stored.id, stored)
    return clone(stored)
  }

  remove(templateId: string): boolean {
    return this.definitions.delete(templateId)
  }

  clear(): void {
    this.definitions.clear()
  }
}
