// Phase 26.21 — Item-backed library provider (base for builtin + imported sources).
//
// Holds pre-built `TemplateLibraryItem` metadata directly (no TemplateProvider, no layout trees)
// — the metadata-first ideal. On register, the item is normalized to this provider's source: the
// source key is forced, the id is recomposed from `<source>:<templateId>`, the thumbnail passes
// the asset URL guard, and the category falls back to the invariant default. Reads return clones,
// so stored metadata is immutable from the outside. This is a discovery store only — it never
// resolves or applies layout.

import { BaseTemplateLibraryProvider } from './BaseTemplateLibraryProvider'
import {
  DEFAULT_TEMPLATE_CATEGORY,
  libraryItemId,
  type TemplateLibraryItem,
} from './TemplateLibraryItem'
import { cloneLibraryItem } from './templateLibraryFilters'
import { sanitizeThumbnail } from './thumbnail'

export class StaticTemplateLibraryProvider extends BaseTemplateLibraryProvider {
  private readonly items = new Map<string, TemplateLibraryItem>()

  constructor(public readonly source: string) {
    super()
  }

  all(): TemplateLibraryItem[] {
    return [...this.items.values()].map(cloneLibraryItem)
  }

  /** Register (or replace by id) an item, normalized to this provider's source. Returns a clone. */
  register(item: TemplateLibraryItem): TemplateLibraryItem {
    const stored = this.normalize(item)
    this.items.set(stored.id, stored)
    return cloneLibraryItem(stored)
  }

  /** Remove an item by its library-wide id. */
  remove(id: string): boolean {
    return this.items.delete(id)
  }

  /** Drop every item. */
  clear(): void {
    this.items.clear()
  }

  private normalize(item: TemplateLibraryItem): TemplateLibraryItem {
    const normalized: TemplateLibraryItem = {
      id: libraryItemId(this.source, item.templateId),
      templateId: item.templateId,
      name: item.name,
      category: item.category ?? DEFAULT_TEMPLATE_CATEGORY,
      source: this.source,
    }
    if (item.description !== undefined) normalized.description = item.description
    if (item.tags !== undefined) normalized.tags = [...item.tags]
    const thumbnail = sanitizeThumbnail(item.thumbnail)
    if (thumbnail !== undefined) normalized.thumbnail = thumbnail
    const previewUrl = sanitizeThumbnail(item.previewUrl)
    if (previewUrl !== undefined) normalized.previewUrl = previewUrl
    return normalized
  }
}
