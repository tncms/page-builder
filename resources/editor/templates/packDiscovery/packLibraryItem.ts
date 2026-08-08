// Phase 26.29 — Pack discovery projection item (metadata-only).
//
// Projects a Phase 26.28 `TemplateStarterPack` into a browsable library entity. It carries ONLY
// discovery metadata plus a template COUNT — never the referenced template definitions, trees, or
// package contents. Packs become discoverable alongside templates without the library owning or
// duplicating any template data.

import { sanitizeThumbnail } from '../library'
import { STARTER_PACK_SOURCE, type TemplateStarterPack } from '../starterPacks'

/** A browsable, metadata-first view of a starter pack. */
export interface TemplatePackLibraryItem {
  /** Library-wide unique id: `<source>:<packId>`. */
  id: string
  /** The underlying `TemplateStarterPack.id`. */
  packId: string
  name: string
  description?: string
  /** An invariant category KEY (never localized). */
  category: string
  /** A thumbnail URL — never a media/attachment id. */
  thumbnail?: string
  /** The invariant source KEY the pack came from. */
  source: string
  /** How many templates the pack references (count only — never the definitions). */
  templateCount: number
}

/** Compose the library-wide unique id for a pack item. Pure, deterministic, invariant. */
export function packLibraryItemId(source: string, packId: string): string {
  return `${source}:${packId}`
}

/** Project a starter pack into a metadata-only discovery item. Pure. */
export function toPackLibraryItem(pack: TemplateStarterPack): TemplatePackLibraryItem {
  const source = pack.source ?? STARTER_PACK_SOURCE.builtin
  const item: TemplatePackLibraryItem = {
    id: packLibraryItemId(source, pack.id),
    packId: pack.id,
    name: pack.name,
    category: pack.category,
    source,
    templateCount: pack.templates.length,
  }
  if (pack.description !== undefined) item.description = pack.description
  const thumbnail = sanitizeThumbnail(pack.thumbnail)
  if (thumbnail !== undefined) item.thumbnail = thumbnail
  return item
}
