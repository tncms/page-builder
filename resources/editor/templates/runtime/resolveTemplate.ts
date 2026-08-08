// Phase 26.17 — the ONE canonical template resolver.
//
// Converts a `TemplateDefinition` into a resolved, independent `PatternTree`. Both layout
// kinds ('tree' and 'patterns') resolve through THIS function — there is no separate apply
// logic elsewhere. Pure: it never mutates the template or any registry data, and it
// deep-clones every tree it returns so the result holds no live references.
//
// Atomic for composed templates: every referenced pattern must resolve before any merge
// happens; a single missing id throws (no partial result, no silent skip/reorder).

import { isTemplateTreeLayout, isTemplatePatternsLayout, type TemplateDefinition, type TemplateLayoutKind } from '../contracts'
import { patternProvider as defaultPatternProvider, type PatternProvider } from '../../patterns/provider'
import type { PatternTree } from '../../patterns/library'
import { TemplateRuntimeError } from './TemplateRuntimeError'

/** The runtime result of resolving a template. Minimal — not a persisted model. */
export interface ResolvedTemplate {
  templateId: string
  tree: PatternTree
  source: TemplateLayoutKind
  patternIds: string[]
}

export interface ResolveTemplateDeps {
  patternProvider?: PatternProvider
}

/** A plain, JSON-safe deep clone. */
function clone<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T
}

/** A value is a valid tree when it has a `sections` array. */
function isValidTree(tree: unknown): tree is PatternTree {
  return !!tree && typeof tree === 'object' && Array.isArray((tree as PatternTree).sections)
}

/** Resolve a template definition into an independent PatternTree + provenance. */
export function resolveTemplate(template: TemplateDefinition, deps: ResolveTemplateDeps = {}): ResolvedTemplate {
  const layout = template.layout
  if (!layout || typeof (layout as { kind?: unknown }).kind !== 'string') {
    throw new TemplateRuntimeError('empty_layout', `Template "${template.id}" has no layout.`, { id: template.id })
  }

  // ---- Inline tree layout ----------------------------------------------------
  if (isTemplateTreeLayout(layout)) {
    if (!isValidTree(layout.tree)) {
      throw new TemplateRuntimeError('invalid_tree', `Template "${template.id}" has an invalid layout tree.`, { id: template.id })
    }
    if (layout.tree.sections.length === 0) {
      throw new TemplateRuntimeError('empty_layout', `Template "${template.id}" has an empty layout tree.`, { id: template.id })
    }
    return {
      templateId: template.id,
      tree: clone({ sections: layout.tree.sections }),
      source: 'tree',
      patternIds: [],
    }
  }

  // ---- Pattern-composition layout -------------------------------------------
  if (isTemplatePatternsLayout(layout)) {
    const ids = layout.patternIds
    if (!Array.isArray(ids) || ids.length === 0) {
      throw new TemplateRuntimeError('empty_layout', `Template "${template.id}" references no patterns.`, { id: template.id })
    }

    const provider = deps.patternProvider ?? defaultPatternProvider

    // Atomic: resolve ALL first; a missing id fails before any merge.
    const resolved = ids.map((id) => ({ id, pattern: provider.get(id) }))
    const missing = resolved.filter((r) => !r.pattern).map((r) => r.id)
    if (missing.length > 0) {
      throw new TemplateRuntimeError('missing_pattern', `Template "${template.id}" references missing pattern(s): ${missing.join(', ')}.`, {
        id: template.id,
        missing,
      })
    }

    // Merge section-rooted trees in DECLARED order; deep-clone so nothing aliases the registry.
    const sections = resolved.flatMap((r) => {
      const tree = r.pattern!.tree
      return isValidTree(tree) ? clone(tree.sections) : []
    })
    if (sections.length === 0) {
      throw new TemplateRuntimeError('empty_layout', `Template "${template.id}" resolved to an empty tree.`, { id: template.id })
    }

    return { templateId: template.id, tree: { sections }, source: 'patterns', patternIds: [...ids] }
  }

  // ---- Unknown kind ----------------------------------------------------------
  throw new TemplateRuntimeError('unsupported_layout_kind', `Template "${template.id}" has an unsupported layout kind.`, {
    id: template.id,
    kind: (layout as { kind?: unknown }).kind,
  })
}
