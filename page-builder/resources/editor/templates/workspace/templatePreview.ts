// Phase 26.36 — template preview foundation (metadata-only, presentation).
//
// Preview is a PRESENTATION capability: it shows an image and metadata for a template before it is
// used. It is NOT a renderer, a builder, or an execution engine — it stores only URLs (never media
// ids, runtime ids, editor state, or copied builder payloads). Both URLs are re-guarded through the
// existing Phase 26.20 asset boundary (defense in depth) so an unsafe URL is never surfaced.

import { sanitizeThumbnail, type TemplateLibraryItem } from '../library'

/** A minimal, metadata-only preview descriptor. URLs only. */
export interface TemplatePreview {
  thumbnail?: string
  previewUrl?: string
}

/** Project an item to its preview descriptor, re-guarding both URLs. Pure. */
export function toTemplatePreview(item: TemplateLibraryItem): TemplatePreview {
  const preview: TemplatePreview = {}
  const thumbnail = sanitizeThumbnail(item.thumbnail)
  if (thumbnail !== undefined) preview.thumbnail = thumbnail
  const previewUrl = sanitizeThumbnail(item.previewUrl)
  if (previewUrl !== undefined) preview.previewUrl = previewUrl
  return preview
}

/** The best preview image URL: prefer the larger `previewUrl`, fall back to the thumbnail. */
export function previewImageUrl(item: TemplateLibraryItem): string | undefined {
  const preview = toTemplatePreview(item)
  return preview.previewUrl ?? preview.thumbnail
}
