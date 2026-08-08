// Phase 26.19 — template management operations (thin, provider-only).
//
// Rename / Duplicate / Delete / Search over the `TemplateProvider`. Every mutation goes
// THROUGH the provider — nothing bypasses it and `LocalTemplateProvider` is not modified.
//
// The provider intentionally has no `duplicate` (templates are page compositions), so a copy
// is composed from existing operations: `get` (returns a deep clone) → fresh unique id via
// the shared Pattern id helper → `register`. This keeps the copy fully independent of the
// original with no aliasing.
//
// Architecture note: `category` is an invariant KEY, never a localized label; search and
// filtering run over stored keys / user-entered names (data), not translated display text.

import { localTemplateProvider } from '../provider'
import { uniqueId } from '../../patterns/library'
import { TEMPLATE_NAME_MAX } from '../authoring'
import { isTemplateTreeLayout, isTemplatePatternsLayout, type TemplateDefinition } from '../contracts'

export interface RenameResult {
  ok: boolean
  template?: TemplateDefinition
  error?: string
}

/** Validate a template name for rename (mirrors authoring's name rule). */
export function validateTemplateName(name: string): string | null {
  const trimmed = (name ?? '').trim()
  if (!trimmed) return 'Name is required.'
  if (trimmed.length > TEMPLATE_NAME_MAX) return `Name must be ${TEMPLATE_NAME_MAX} characters or fewer.`
  return null
}

/** Rename a template in place (id and list position preserved by the provider). */
export function renameTemplate(id: string, name: string): RenameResult {
  const error = validateTemplateName(name)
  if (error) return { ok: false, error }
  const updated = localTemplateProvider.update(id, { name: name.trim() })
  if (!updated) return { ok: false, error: 'Template not found.' }
  return { ok: true, template: updated }
}

/**
 * Create an INDEPENDENT copy: a fresh unique id, the original's deep-cloned layout/metadata,
 * and a "<name> Copy" name. Composed from `get` + `register` (no provider `duplicate`). The
 * original is never touched. Returns the copy, or null when the id is unknown.
 */
export function duplicateTemplate(id: string): TemplateDefinition | null {
  const source = localTemplateProvider.get(id) // already a deep clone
  if (!source) return null
  const copy: TemplateDefinition = { ...source, id: uniqueId('template'), name: `${source.name} Copy` }
  return localTemplateProvider.register(copy)
}

/** Remove a template. Already-applied content is independent and unaffected. */
export function deleteTemplate(id: string): boolean {
  return localTemplateProvider.remove(id)
}

/**
 * A short, key-based summary of a template's composition for list display: section count for
 * a tree layout, pattern count for a pattern-composition layout. Pure, no localization.
 */
export function templateSummary(template: TemplateDefinition): string {
  const layout = template.layout
  if (isTemplateTreeLayout(layout)) {
    const n = layout.tree.sections.length
    return `${n} section${n === 1 ? '' : 's'}`
  }
  if (isTemplatePatternsLayout(layout)) {
    const n = layout.patternIds.length
    return `${n} pattern${n === 1 ? '' : 's'}`
  }
  return ''
}

/**
 * Filter templates by a name/description substring (case-insensitive) and an optional
 * category KEY. Pure — operates on the passed snapshot, never the provider.
 */
export function searchTemplates(
  templates: TemplateDefinition[],
  query: string,
  category: string | null,
): TemplateDefinition[] {
  const q = query.trim().toLowerCase()
  return templates.filter((t) => {
    if (category && t.category !== category) return false
    if (!q) return true
    return t.name.toLowerCase().includes(q) || (t.description ?? '').toLowerCase().includes(q)
  })
}
