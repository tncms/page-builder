// Phase 26.21 — Template Library item contract (metadata-first).
//
// The library is a DISCOVERY layer: browsing surfaces lightweight metadata, never full
// TemplateDefinition trees. A `TemplateLibraryItem` describes a template just enough to list,
// search, filter, and preview it; the heavy layout is fetched from the TemplateProvider only
// when a template is actually applied (a FUTURE concern, not this phase).
//
// Asset boundary (Phase 26.20.1): `thumbnail` is a URL ONLY. Never a media id, attachment id,
// storage path, or CDN provider metadata — templates must stay portable.

/** Fallback category KEY for a template that declares none. An invariant key, never localized. */
export const DEFAULT_TEMPLATE_CATEGORY = 'uncategorized'

/**
 * A browsable, metadata-first view of a template.
 *
 * `id` is a stable composite key (`<source>:<templateId>`) unique across the whole library.
 * `templateId` references the underlying `TemplateDefinition.id` within its source. `category`
 * and `source` are invariant KEYS; `name`/`description` are content data (a user's own words).
 */
export interface TemplateLibraryItem {
  /** Library-wide unique id: `<source>:<templateId>`. */
  id: string
  /** The underlying `TemplateDefinition` id within its source. */
  templateId: string
  name: string
  description?: string
  /** An invariant category KEY (never a localized label). */
  category: string
  /** A thumbnail URL — never a media/attachment id or storage metadata. */
  thumbnail?: string
  /** A larger preview image URL (Phase 26.36) — a URL only, same asset boundary as `thumbnail`. */
  previewUrl?: string
  /** Free-form invariant tag keys used for search/filtering. */
  tags?: string[]
  /** The invariant source key this item was discovered through. */
  source: string
}

/** Compose the library-wide unique id for an item. Pure, deterministic, invariant. */
export function libraryItemId(source: string, templateId: string): string {
  return `${source}:${templateId}`
}
