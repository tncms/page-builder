// Phase 26.13 — build a PatternDefinition from a selected component (pure).
//
// Reuses the Phase 26.11 serializer verbatim (`createPatternDefinition` → `serializePattern`):
// it strips ids and copies `data` (responsive values, token refs, style bindings, state
// styles) untouched. This module only adds the "root wrapping" so a saved layout block
// becomes a valid SECTION-rooted tree that the existing `insert-pattern` command can
// replay unchanged.
//
// Wrapping (patterns insert as root sections; sections are the only valid root):
//   • section   → used as-is
//   • container → wrapped in a section          (section > container)
//   • column    → wrapped in a section+container (section > container > column, per nesting)
//
// The wrappers are synthetic id-free nodes with minimal data; the server assigns ids and
// the renderer applies component defaults on insert. The source node is never mutated
// (it is already a clone from `findComponentNode`, and the serializer clones again).

import { createPatternDefinition, type PatternDefinition } from '../library'
import type { BlockNode, ComponentKind, SectionNode } from '../../types'

/** Wrap a selected root into valid section-rooted nodes for the serializer. */
export function wrapRootAsSections(node: SectionNode | BlockNode, kind: ComponentKind): SectionNode[] {
  if (kind === 'section') return [node as SectionNode]

  const block = node as BlockNode
  if (block.type === 'column') {
    // A column must live inside a container — synthesize one so nesting stays valid.
    return [{ type: 'section', data: {}, blocks: [{ type: 'container', data: { blocks: [block] } }] }]
  }
  // container (or any other layout host) → a section wrapper is enough.
  return [{ type: 'section', data: {}, blocks: [block] }]
}

export interface SavePatternInput {
  node: SectionNode | BlockNode
  kind: ComponentKind
  name: string
  category: string
  description?: string
}

/** Build a registerable `PatternDefinition` from a selected component + metadata. */
export function buildPatternDefinition(input: SavePatternInput): PatternDefinition {
  const description = input.description?.trim()
  return createPatternDefinition({
    name: input.name.trim(),
    category: input.category,
    description: description ? description : undefined,
    sections: wrapRootAsSections(input.node, input.kind),
  })
}
