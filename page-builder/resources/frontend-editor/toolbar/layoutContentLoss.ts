// Phase 26.1 — Pure content-loss detection for the layout-preset popup.
//
// Applying a preset with FEWER widths than a container's current column count
// drops the trailing columns (server-side, index-based). If any dropped column
// holds child blocks, the popup confirms before dispatching. This module answers
// that question from the payload alone (no Vue/Pinia, no DOM) so it is trivially
// testable.

import { findComponentNode } from '../../editor/utils/layout'
import type { LayoutPayload } from '../../editor/types'

/** The blocks (columns) directly held by a container node, or []. */
function containerColumns(
  payload: LayoutPayload | null | undefined,
  containerId: string,
): Array<Record<string, unknown>> {
  const hit = findComponentNode(payload, containerId)
  const data = hit?.node?.data as Record<string, unknown> | undefined
  const blocks = data?.blocks
  return Array.isArray(blocks) ? (blocks as Array<Record<string, unknown>>) : []
}

/** Does a single column node hold any child blocks? */
function columnHasContent(column: Record<string, unknown>): boolean {
  const data = column?.data as Record<string, unknown> | undefined
  const blocks = data?.blocks
  return Array.isArray(blocks) && blocks.length > 0
}

/**
 * Would applying `widthCount` widths to the container drop a column that holds
 * content? True only when the preset has fewer widths than the current column
 * count AND at least one to-be-dropped (trailing) column contains child blocks.
 */
export function presetLosesContent(
  payload: LayoutPayload | null | undefined,
  containerId: string,
  widthCount: number,
): boolean {
  const columns = containerColumns(payload, containerId)
  if (widthCount >= columns.length) return false
  return columns.slice(widthCount).some((column) => columnHasContent(column))
}
