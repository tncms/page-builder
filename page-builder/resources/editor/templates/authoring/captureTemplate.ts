// Phase 26.18 — build a TemplateDefinition from the current editor layout (pure).
//
// Reuses the Phase 26.11 serializer verbatim (`serializePattern` → `serializeSection`): it
// strips runtime ids and copies `data` (responsive values, token refs, style bindings,
// state styles) untouched, and the id (via `uniqueId`) comes from the same Pattern id
// helper. This module only decides WHICH sections to serialize (selection vs whole page)
// and wraps a container selection into a valid SECTION-rooted tree.
//
// The output is a `tree`-kind `TemplateDefinition`, compatible with `resolveTemplate()`
// without any second layout format. The source editor state is never mutated (the serializer
// deep-clones every node's data).

import { serializePattern, uniqueId, type PatternTree } from '../../patterns/library'
import type { BlockNode, ComponentKind, SectionNode } from '../../types'
import type { TemplateDefinition } from '../contracts'

/**
 * Wrap a captured selection into valid section-rooted nodes for the serializer.
 *   • section   → used as-is
 *   • container → wrapped in a synthetic section (section > container)
 * Columns and leaf blocks never reach here — `isSupportedTemplateRoot` rejects them first.
 */
export function wrapSelectionAsSections(node: SectionNode | BlockNode, kind: ComponentKind): SectionNode[] {
  if (kind === 'section') return [node as SectionNode]
  return [{ type: 'section', data: {}, blocks: [node as BlockNode] }]
}

export interface BuildTemplateInput {
  /** Section-rooted nodes to serialize (from a selection wrap or the full page). */
  sections: SectionNode[]
  name: string
  category: string
  description?: string
  /** Optional thumbnail URL (metadata-only preview reference; never a media id). */
  preview?: string
}

/** Build a registerable `TemplateDefinition` (tree layout) from sections + metadata. */
export function buildTemplateDefinition(input: BuildTemplateInput): TemplateDefinition {
  const description = input.description?.trim()
  const preview = input.preview?.trim()
  const tree: PatternTree = serializePattern(input.sections)
  return {
    id: uniqueId('template'),
    name: input.name.trim(),
    category: input.category,
    description: description ? description : undefined,
    preview: preview ? preview : undefined,
    layout: { kind: 'tree', tree },
  }
}
