// Phase 26.31 — adoption-aware discovery item (installed reference enriched via the library).
//
// An `InstalledTemplateItem` reference joined to the existing Template Library — the discovery
// FOUNDATION — for its display metadata (name/description/category/thumbnail/tags). It never copies
// a template definition or tree; enrichment reads a metadata-first `TemplateLibraryItem` snapshot.
// `available` is the availability indicator: true when the referenced template resolves in the
// library. Missing library metadata does not hide the item — an installed template stays
// discoverable by reference, just marked unavailable.

import { libraryItemId, type TemplateLibraryProvider } from '../library'
import type { InstalledTemplateItem } from './installedTemplateItem'

/** An installed template, enriched with library discovery metadata + an availability indicator. */
export interface AdoptionTemplateItem extends InstalledTemplateItem {
  /** Library-wide id: `<source>:<templateId>` (matches the Template Library's item id). */
  id: string
  /** True when the referenced template resolves in the Template Library (the discovery foundation). */
  available: boolean
  name?: string
  description?: string
  /** An invariant category KEY (never a localized label), from the library item when available. */
  category?: string
  /** A thumbnail URL — never a media/attachment id (inherited from the library item). */
  thumbnail?: string
  /** Free-form invariant tag keys used for search/filtering. */
  tags?: string[]
}

/**
 * Join one installed reference to its Template Library metadata. Pure: it only reads a library
 * snapshot via `find` — it never resolves, mutates, or applies a template. When the library has no
 * matching item the result is `available: false` with reference-only fields.
 */
export function toAdoptionItem(
  installed: InstalledTemplateItem,
  library: TemplateLibraryProvider,
): AdoptionTemplateItem {
  const id = libraryItemId(installed.source, installed.templateId)
  const libItem = library.find(id)
  const item: AdoptionTemplateItem = {
    id,
    templateId: installed.templateId,
    source: installed.source,
    available: libItem !== undefined,
  }
  if (installed.installedFromPack !== undefined) item.installedFromPack = installed.installedFromPack
  if (libItem) {
    item.name = libItem.name
    item.category = libItem.category
    if (libItem.description !== undefined) item.description = libItem.description
    if (libItem.thumbnail !== undefined) item.thumbnail = libItem.thumbnail
    if (libItem.tags !== undefined) item.tags = [...libItem.tags]
  }
  return item
}
