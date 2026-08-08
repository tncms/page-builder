// Phase 26.32 — template usage intent (pure translation, no execution).
//
// Turns a Phase 26.31 `AdoptionTemplateItem` (a discovered/adopted template) into the
// `TemplateSelection` identity the EXISTING Phase 26.23 apply flow already consumes. This is the
// "Intent" step of the usage workflow: it CONNECTS discovery to apply and does nothing else — it
// resolves no layout tree, calls no `resolveTemplate`, runs no command, and never touches
// BuilderState. Only an AVAILABLE template (one that resolves in the Template Library) can be used;
// an unavailable reference yields null, never a half-built selection.

import { DEFAULT_TEMPLATE_CATEGORY, type TemplateLibraryItem } from '../library'
import type { TemplateSelection } from '../picker'
import type { AdoptionTemplateItem } from '../adoptionDiscovery'

/** Why a usage intent could not proceed. */
export type UsageIntentReason = 'not-found' | 'unavailable'

/** The outcome of resolving a usage intent — a ready `TemplateSelection` or a reason it can't run. */
export interface UsageIntentResult {
  ok: boolean
  reason?: UsageIntentReason
  selection?: TemplateSelection
}

/**
 * Rebuild the library-item identity an adoption item was enriched from, and wrap it as a
 * `TemplateSelection`. Pure. Returns null when the item is not available (no library metadata to
 * hand off) — usage requires a resolvable template.
 */
export function toUsageSelection(item: AdoptionTemplateItem): TemplateSelection | null {
  if (!item.available || item.name === undefined) return null
  const libraryItem: TemplateLibraryItem = {
    id: item.id,
    templateId: item.templateId,
    name: item.name,
    category: item.category ?? DEFAULT_TEMPLATE_CATEGORY,
    source: item.source,
  }
  if (item.description !== undefined) libraryItem.description = item.description
  if (item.thumbnail !== undefined) libraryItem.thumbnail = item.thumbnail
  if (item.tags !== undefined) libraryItem.tags = [...item.tags]
  return { templateId: item.templateId, source: item.source, item: libraryItem }
}
