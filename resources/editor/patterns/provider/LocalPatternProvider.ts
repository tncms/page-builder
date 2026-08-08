// Phase 26.15 — the default provider: a thin delegate to the in-memory registry.
//
// NO business logic, persistence, caching, or API — every call forwards straight to the
// Phase 26.11 `patternLibraryRegistry`. This is the single place that knows the storage
// is the local registry; swap the exported `patternProvider` to introduce another.

import { patternLibraryRegistry, type PatternDefinition } from '../library'
import type { PatternProvider } from './PatternProvider'

export class LocalPatternProvider implements PatternProvider {
  all(): PatternDefinition[] {
    return patternLibraryRegistry.all()
  }

  get(id: string): PatternDefinition | undefined {
    return patternLibraryRegistry.get(id)
  }

  register(pattern: PatternDefinition): PatternDefinition {
    return patternLibraryRegistry.register(pattern)
  }

  update(id: string, patch: Partial<Omit<PatternDefinition, 'id'>>): PatternDefinition | undefined {
    return patternLibraryRegistry.update(id, patch)
  }

  duplicate(id: string, overrides?: Partial<Omit<PatternDefinition, 'id' | 'tree'>>): PatternDefinition | undefined {
    return patternLibraryRegistry.duplicate(id, overrides)
  }

  remove(id: string): boolean {
    return patternLibraryRegistry.remove(id)
  }
}

/** The default local provider instance. */
export const localPatternProvider = new LocalPatternProvider()
