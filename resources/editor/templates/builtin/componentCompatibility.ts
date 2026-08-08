// Phase 26.25 — component compatibility for built-in template trees.
//
// Walks a template's `PatternTree` and reports issues: missing types, invalid nesting (via the
// existing static `canContain` policy — needs no metadata), and — when editor metadata is
// supplied — unknown section/block types. Type-existence is PERMISSIVE when no metadata is given
// (mirrors `validateLayout`), so packs validate structurally at boot and fully in tests/tools.
//
// Built-in template trees are id-free by design, so — unlike `validateLayout` — this does NOT
// check ids. Nested block children follow the canonical asymmetry: a section's children are
// `section.blocks[]`, a block's children are `block.data.blocks[]`.

import type { PatternTree, PatternBlockSnapshot } from '../../patterns/library'
import type { EditorMetadata } from '../../types'
import { canContain } from '../../utils/nestingPolicy'
import type { PackValidationIssue } from './builtinPackTypes'

export interface CompatibilityOptions {
  metadata?: Pick<EditorMetadata, 'sections' | 'blocks'>
}

/** Collect compatibility issues for a template tree. Empty array = compatible. Pure. */
export function collectTreeIssues(tree: PatternTree, options: CompatibilityOptions = {}): PackValidationIssue[] {
  const issues: PackValidationIssue[] = []
  const knownSections = options.metadata ? new Set(options.metadata.sections.map((d) => d.type)) : null
  const knownBlocks = options.metadata ? new Set(options.metadata.blocks.map((d) => d.type)) : null

  const sections = tree?.sections
  if (!Array.isArray(sections) || sections.length === 0) {
    issues.push({ code: 'empty-tree', message: 'Template tree has no sections.' })
    return issues
  }

  const walkBlock = (parentType: string, block: PatternBlockSnapshot): void => {
    if (!block?.type) {
      issues.push({ code: 'missing-type', message: 'A block is missing its type.' })
      return
    }
    if (knownBlocks && !knownBlocks.has(block.type)) {
      issues.push({ code: 'unknown-block', message: `Unknown block type "${block.type}".` })
    }
    if (!canContain(parentType, block.type)) {
      issues.push({ code: 'invalid-nesting', message: `"${block.type}" cannot be nested inside "${parentType}".` })
    }
    const nested = block.data?.blocks
    if (Array.isArray(nested)) {
      for (const child of nested as PatternBlockSnapshot[]) walkBlock(block.type, child)
    }
  }

  for (const section of sections) {
    if (!section?.type) {
      issues.push({ code: 'missing-type', message: 'A section is missing its type.' })
      continue
    }
    if (knownSections && !knownSections.has(section.type)) {
      issues.push({ code: 'unknown-section', message: `Unknown section type "${section.type}".` })
    }
    for (const block of section.blocks ?? []) walkBlock(section.type, block)
  }

  return issues
}
