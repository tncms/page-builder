// Phase 26.35 — export workspace templates as a portable package (reuses Phase 26.26).
//
// The workspace holds library metadata, so export resolves each item's DEFINITION through the Phase
// 26.24 source resolver (read-only — no runtime, no BuilderState), then hands the definitions to the
// existing `exportTemplatePackage` / `serializeTemplatePackage`. It returns the download artifact
// (filename + JSON); the UI performs the actual browser download. Nothing new downstream.

import { templateSourceResolver } from '../source'
import { exportTemplatePackage, serializeTemplatePackage, type TemplatePackageMetadata } from '../package'
import type { TemplateLibraryItem } from '../library'

export interface WorkspaceExportResult {
  ok: boolean
  /** Number of templates whose definitions were resolved and included. */
  count: number
  filename?: string
  json?: string
  reason?: string
}

/** Turn a JSON-safe filename from a package id (fallback: `templates`). */
function toFilename(id: string): string {
  const base = id.trim().toLowerCase().replace(/[^a-z0-9._-]+/g, '-').replace(/^-+|-+$/g, '')
  return `${base || 'templates'}.json`
}

/**
 * Export the given library items as a serialized package. Items whose definition cannot be resolved
 * are skipped; if none resolve, the export is rejected (nothing to download). Pure aside from the
 * read-only resolver lookup.
 */
export function exportWorkspaceTemplates(
  items: readonly TemplateLibraryItem[],
  metadata: TemplatePackageMetadata,
): WorkspaceExportResult {
  const definitions = items
    .map((item) => templateSourceResolver.getDefinition(item.templateId, item.source))
    .filter((def): def is NonNullable<typeof def> => def !== null)

  if (definitions.length === 0) {
    return { ok: false, count: 0, reason: 'no-templates' }
  }

  const pkg = exportTemplatePackage(definitions, metadata)
  return {
    ok: true,
    count: definitions.length,
    filename: toFilename(metadata.id),
    json: serializeTemplatePackage(pkg),
  }
}
