// Phase 26.1.1 — Pure preview-segment resolution for a column-layout preset.
//
// A preset may carry a rich `preview` descriptor ({ type: 'bars', segments }) or
// only its `widths`. This is the single place that turns either into the numeric
// segment weights the shared PresetPreview.vue renderer draws as proportion bars,
// so no SVG/flex logic is ever duplicated. Non-numeric or empty inputs fall back
// to a single equal segment (a preset always previews as at least one bar).

import type { ColumnLayoutPreset, LayoutPresetPreview } from '../../editor/types'

/** Coerce a width string/number into a positive weight, or null when invalid. */
function toWeight(value: unknown): number | null {
  const n = typeof value === 'number' ? value : Number(value)
  return Number.isFinite(n) && n > 0 ? n : null
}

/**
 * Resolve the proportion-bar segments for a preset. Prefers the `preview`
 * descriptor's segments; otherwise derives them from `widths`. Always returns at
 * least one positive weight so the renderer never draws an empty preview.
 */
export function previewSegments(preset: {
  widths?: string[]
  preview?: LayoutPresetPreview
}): number[] {
  const fromPreview = preset.preview?.segments
  if (Array.isArray(fromPreview) && fromPreview.length > 0) {
    const cleaned = fromPreview.map(toWeight).filter((n): n is number => n !== null)
    if (cleaned.length > 0) return cleaned
  }

  const fromWidths = (preset.widths ?? [])
    .map(toWeight)
    .filter((n): n is number => n !== null)
  return fromWidths.length > 0 ? fromWidths : [1]
}

/** The preview `type` a preset declares, defaulting to 'bars'. */
export function previewType(preset: Pick<ColumnLayoutPreset, 'preview'>): string {
  return preset.preview?.type || 'bars'
}
