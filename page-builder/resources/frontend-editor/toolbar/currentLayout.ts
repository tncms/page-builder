// Phase 26.1.1 — Pure current-layout detection for the Layout popup.
//
// Given the selected container's current column widths and the preset list,
// resolve which preset (if any) the container currently matches — so the popup
// can badge it "✓ Current" (or show "Custom Layout" when none match). No Vue,
// no DOM: it reads column widths from the payload and compares numerically.
//
// Tolerance: percent widths like 33 / 33.33 / 33.3333 must count as equal, so we
// compare each column's share to ~1% (EPSILON). Widths are also normalised to a
// share of their own sum first, so ['50','50'] and ['1','1'] match the same way.

import { findComponentNode } from '../../editor/utils/layout'
import type { ColumnLayoutPreset, LayoutPayload } from '../../editor/types'

// ~1% of a full column — treats 33 / 33.33 / 33.3333 as the same third.
const EPSILON = 1

/** Numeric column widths of a container (from each column child's data.width). */
export function containerColumnWidths(
  payload: LayoutPayload | null | undefined,
  containerId: string | null,
): number[] {
  if (!containerId) return []
  const hit = findComponentNode(payload, containerId)
  const data = hit?.node?.data as Record<string, unknown> | undefined
  const blocks = data?.blocks
  if (!Array.isArray(blocks)) return []
  return blocks.map((col) => {
    const colData = (col as Record<string, unknown>)?.data as Record<string, unknown> | undefined
    const n = Number(colData?.width)
    return Number.isFinite(n) ? n : 0
  })
}

/** Normalise a width list to percentage shares of their own sum. */
function toShares(widths: number[]): number[] {
  const total = widths.reduce((sum, w) => sum + (w > 0 ? w : 0), 0)
  if (total <= 0) return widths.map(() => 0)
  return widths.map((w) => ((w > 0 ? w : 0) / total) * 100)
}

/** Do two width lists describe the same proportions within tolerance? */
export function widthsMatch(a: number[], b: number[]): boolean {
  if (a.length !== b.length || a.length === 0) return false
  const sharesA = toShares(a)
  const sharesB = toShares(b)
  return sharesA.every((share, i) => Math.abs(share - sharesB[i]) <= EPSILON)
}

/**
 * The id of the preset whose widths match `widths`, or null ("Custom Layout").
 * Presets are compared in list order; the first match wins.
 */
export function matchPresetId(
  widths: number[],
  presets: ColumnLayoutPreset[],
): string | null {
  if (widths.length === 0) return null
  for (const preset of presets) {
    const presetWidths = (preset.widths ?? []).map((w) => Number(w))
    if (widthsMatch(widths, presetWidths)) return preset.id
  }
  return null
}

/**
 * The preset id matching the container's current column widths, or null.
 * Convenience wrapper reading straight from the payload.
 */
export function currentLayoutPresetId(
  payload: LayoutPayload | null | undefined,
  containerId: string | null,
  presets: ColumnLayoutPreset[],
): string | null {
  return matchPresetId(containerColumnWidths(payload, containerId), presets)
}
