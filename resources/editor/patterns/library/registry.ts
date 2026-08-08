// Phase 26.11 — the Pattern Library registry (foundation).
//
// An in-memory store of authored `PatternDefinition`s and the seam a future picker lists
// from. Intentionally NOT persisted — no storage, no migration, no cloud sync (all out
// of scope by design). A host that wants durability can serialize `all()` itself later.

import { serializePattern } from './serialize'
import { uniqueId } from './ids'
import type { PatternDefinition, PatternTree } from './patternDefinition'
import type { SectionNode } from '../../types'

/** A plain deep clone of a pattern tree (already JSON-safe from serialization). */
function clonePatternTree(tree: PatternTree): PatternTree {
  return JSON.parse(JSON.stringify(tree)) as PatternTree
}

/** Build a `PatternDefinition` from live sections (serializes them into an id-free tree). */
export function createPatternDefinition(input: {
  name: string
  sections: SectionNode[]
  id?: string
  category?: string
  description?: string
}): PatternDefinition {
  return {
    id: input.id ?? uniqueId('pattern'),
    name: input.name,
    category: input.category,
    description: input.description,
    tree: serializePattern(input.sections),
  }
}

/** In-memory registry of pattern definitions. */
export class PatternLibraryRegistry {
  private patterns = new Map<string, PatternDefinition>()

  /** Register (or replace, by id) a pattern. Returns the stored definition. */
  register(pattern: PatternDefinition): PatternDefinition {
    this.patterns.set(pattern.id, pattern)
    return pattern
  }

  get(id: string): PatternDefinition | undefined {
    return this.patterns.get(id)
  }

  has(id: string): boolean {
    return this.patterns.has(id)
  }

  /** Every registered pattern, in insertion order — the listing foundation. */
  all(): PatternDefinition[] {
    return [...this.patterns.values()]
  }

  /** Patterns filtered to a category — the picker's group seam. */
  byCategory(category: string): PatternDefinition[] {
    return this.all().filter((pattern) => pattern.category === category)
  }

  /**
   * Merge a patch into an existing pattern, KEEPING its id and its position (a Map
   * `set` on an existing key preserves insertion order). Returns the updated pattern, or
   * undefined when the id is unknown. Powers rename without minting a new id.
   */
  update(id: string, patch: Partial<Omit<PatternDefinition, 'id'>>): PatternDefinition | undefined {
    const current = this.patterns.get(id)
    if (!current) return undefined
    const updated: PatternDefinition = { ...current, ...patch, id }
    this.patterns.set(id, updated)
    return updated
  }

  /**
   * Create an INDEPENDENT copy: a fresh unique id + a deep-cloned tree, preserving
   * category/description. The default name is "<name> Copy"; `overrides` can set another.
   * The original is never touched. Returns the copy, or undefined when the id is unknown.
   */
  duplicate(id: string, overrides: Partial<Omit<PatternDefinition, 'id' | 'tree'>> = {}): PatternDefinition | undefined {
    const current = this.patterns.get(id)
    if (!current) return undefined
    const copy: PatternDefinition = {
      ...current,
      ...overrides,
      id: uniqueId('pattern'),
      name: overrides.name ?? `${current.name} Copy`,
      tree: clonePatternTree(current.tree),
    }
    this.patterns.set(copy.id, copy)
    return copy
  }

  remove(id: string): boolean {
    return this.patterns.delete(id)
  }

  clear(): void {
    this.patterns.clear()
  }
}

/** App-wide singleton (mirrors the other Phase 26 registries). */
export const patternLibraryRegistry = new PatternLibraryRegistry()
