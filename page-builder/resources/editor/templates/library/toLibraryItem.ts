// Phase 26.21 — Map a TemplateDefinition to a metadata-first library item.
//
// This is the DISCOVERY projection: it keeps id/name/description/category/thumbnail and drops the
// layout tree entirely. Browsing therefore never carries a full TemplateDefinition. The category
// falls back to an invariant KEY; the thumbnail passes through the asset URL guard.

import type { TemplateDefinition } from '../contracts'
import {
  DEFAULT_TEMPLATE_CATEGORY,
  libraryItemId,
  type TemplateLibraryItem,
} from './TemplateLibraryItem'
import { sanitizeThumbnail } from './thumbnail'

/** Project a `TemplateDefinition` into a `TemplateLibraryItem` for the given source. Pure. */
export function toLibraryItem(def: TemplateDefinition, source: string): TemplateLibraryItem {
  const item: TemplateLibraryItem = {
    id: libraryItemId(source, def.id),
    templateId: def.id,
    name: def.name,
    category: def.category ?? DEFAULT_TEMPLATE_CATEGORY,
    source,
  }
  if (def.description !== undefined) item.description = def.description
  const thumbnail = sanitizeThumbnail(def.preview)
  if (thumbnail !== undefined) item.thumbnail = thumbnail
  return item
}
