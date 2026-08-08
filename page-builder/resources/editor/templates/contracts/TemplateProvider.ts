// Phase 26.16 — TemplateProvider contract (design only, NO storage).
//
// Mirrors the Phase 26.15 `PatternProvider` seam so the same swap-a-provider pattern
// applies to templates later: a future `LocalTemplateProvider` (or a database/theme one)
// implements this without any UI change. Templates are page compositions, so there is no
// `duplicate` here — just the lifecycle operations a template manager needs.
//
// This phase implements NONE of it. There is deliberately no LocalTemplateProvider file,
// no registry, and no persistence — only the contract.

import type { TemplateDefinition } from './TemplateDefinition'

export interface TemplateProvider {
  /** Every registered template, in registration order. */
  all(): TemplateDefinition[]
  /** One template by id, or undefined. */
  get(id: string): TemplateDefinition | undefined
  /** Register (or replace by id) a template. */
  register(template: TemplateDefinition): TemplateDefinition
  /** Merge a patch into a template, preserving its id. */
  update(id: string, patch: Partial<Omit<TemplateDefinition, 'id'>>): TemplateDefinition | undefined
  /** Remove a template. */
  remove(id: string): boolean
}
