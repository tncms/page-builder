// Phase 26.17 — the local, in-memory TemplateProvider implementation.
//
// A thin store for the Phase 26.16 `TemplateProvider` contract. In-memory only, JSON-safe,
// deterministic, isolated inside `templates/`, and INDEPENDENT of the pattern registry. It
// is the swap point for a future persistent provider. NOT a Repository/Manager/Gateway/
// Bus/Service — just the contract operations over a Map.
//
// Stricter than the pattern registry by design: `register` REJECTS a duplicate id (no
// accidental overwrite — use `update` to modify), and every read/write is deep-cloned so
// callers can never mutate the stored templates.

import type { TemplateDefinition, TemplateProvider } from '../contracts'
import { TemplateRuntimeError } from '../runtime/TemplateRuntimeError'

/** A plain, JSON-safe deep clone (template metadata + PatternTree / ids are all JSON). */
function clone<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T
}

export class LocalTemplateProvider implements TemplateProvider {
  private templates = new Map<string, TemplateDefinition>()

  /** Every template, in registration order — deep-cloned. */
  all(): TemplateDefinition[] {
    return [...this.templates.values()].map(clone)
  }

  /** One template by id — deep-cloned, or undefined. */
  get(id: string): TemplateDefinition | undefined {
    const template = this.templates.get(id)
    return template ? clone(template) : undefined
  }

  /** Register a NEW template. Throws on a duplicate id (no accidental overwrite). */
  register(template: TemplateDefinition): TemplateDefinition {
    if (this.templates.has(template.id)) {
      throw new TemplateRuntimeError('duplicate_registration', `Template "${template.id}" is already registered.`, { id: template.id })
    }
    const stored = clone(template)
    this.templates.set(stored.id, stored)
    return clone(stored)
  }

  /** Merge a patch, preserving id and list position. Undefined when the id is unknown. */
  update(id: string, patch: Partial<Omit<TemplateDefinition, 'id'>>): TemplateDefinition | undefined {
    const current = this.templates.get(id)
    if (!current) return undefined
    const updated = clone({ ...current, ...patch, id }) // Map.set on the same key keeps position
    this.templates.set(id, updated)
    return clone(updated)
  }

  /** Remove a template. */
  remove(id: string): boolean {
    return this.templates.delete(id)
  }

  /** Drop all templates (test / host reset). */
  clear(): void {
    this.templates.clear()
  }
}

/** The default local provider instance. */
export const localTemplateProvider = new LocalTemplateProvider()
