// Phase 22 — Pure structural helpers for the in-canvas toolbar. No Vue/Pinia,
// no commands: just the index/selection math the store delegates to. Every
// function derives from the payload via the shared layout utilities, never from
// the DOM.

import { childIds, componentLocation } from '../../editor/utils/layout'
import type { LayoutPayload } from '../../editor/types'

// `childIds` and `selectionAfterDelete` are the shared delete-fallback math,
// now owned by the editor layout utils so the store, the navigator menu and the
// canvas toolbars all agree on one implementation (spec §4). Re-exported here so
// existing toolbar imports/tests keep resolving from this module.
export { childIds, selectionAfterDelete } from '../../editor/utils/layout'

/** True when `id` can move one slot toward the start of its parent. */
export function canMoveUp(payload: LayoutPayload | null | undefined, id: string | null): boolean {
  const loc = id ? componentLocation(payload, id) : null
  return loc !== null && loc.index > 0
}

/** True when `id` can move one slot toward the end of its parent. */
export function canMoveDown(payload: LayoutPayload | null | undefined, id: string | null): boolean {
  const loc = id ? componentLocation(payload, id) : null
  if (!loc) return false
  return loc.index < childIds(payload, loc.parentId).length - 1
}

/**
 * The post-removal target index for a same-parent move, or null when the node
 * can't move that direction. The move-component command removes the node before
 * re-inserting, so the target index is expressed in the array *after* removal:
 * up → index - 1, down → index + 1.
 */
export function moveIndex(
  payload: LayoutPayload | null | undefined,
  id: string | null,
  direction: 'up' | 'down',
): { parentId: string | null; index: number } | null {
  if (direction === 'up' ? !canMoveUp(payload, id) : !canMoveDown(payload, id)) return null
  const loc = componentLocation(payload, id)
  if (!loc) return null
  return {
    parentId: loc.parentId,
    index: direction === 'up' ? loc.index - 1 : loc.index + 1,
  }
}

/**
 * Where a clipboard node should be inserted to become a sibling before/after
 * `id`. Returns the parent id + insertion index, or null when `id` isn't found.
 * The caller still validates kind-vs-parent (a block can't sit at the root).
 */
export function siblingInsert(
  payload: LayoutPayload | null | undefined,
  id: string | null,
  position: 'before' | 'after',
): { parentId: string | null; index: number } | null {
  const loc = id ? componentLocation(payload, id) : null
  if (!loc) return null
  return {
    parentId: loc.parentId,
    index: position === 'after' ? loc.index + 1 : loc.index,
  }
}
