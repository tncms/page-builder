// Phase 22.1 — Pure drop-index math for cross-iframe drag commits.
//
// The iframe resolves *where* (parent + edge + reference component); the shell
// turns that into the numeric index the move/insert command needs, against the
// authoritative payload. Ported verbatim from the structure editor's store
// (dropIndexFor / insertIndexFor) so both editors commit identically:
//
//   • move-component removes the node before re-inserting, so a same-parent
//     reorder to a later slot is compensated by one.
//   • insert-component adds without removing, so no compensation.

import { componentLocation, childrenCount } from '../../editor/utils/layout'
import type { LayoutPayload } from '../../editor/types'
import type { LiveDropResolution } from './types'

interface SubjectLocation {
  parentId: string | null
  index: number
}

/** The index for a MOVE (existing component), or null when unresolvable. */
export function moveDropIndex(
  payload: LayoutPayload | null | undefined,
  subject: SubjectLocation,
  res: Pick<LiveDropResolution, 'edge' | 'parentId' | 'referenceId'>,
): number | null {
  if (res.edge === 'inside') {
    const count = childrenCount(payload, res.parentId)
    return subject.parentId === res.parentId ? Math.max(0, count - 1) : count
  }

  const ref = res.referenceId ? componentLocation(payload, res.referenceId) : null
  if (!ref) return null

  let index = res.edge === 'after' ? ref.index + 1 : ref.index
  if (subject.parentId === res.parentId && subject.index < index) index -= 1
  return index
}

/** The index for an INSERT (palette component); appends inside, else the slot. */
export function insertDropIndex(
  payload: LayoutPayload | null | undefined,
  res: Pick<LiveDropResolution, 'edge' | 'parentId' | 'referenceId'>,
): number {
  if (res.edge === 'inside' || !res.referenceId) {
    return childrenCount(payload, res.parentId)
  }
  const ref = componentLocation(payload, res.referenceId)
  if (!ref) return childrenCount(payload, res.parentId)
  return res.edge === 'after' ? ref.index + 1 : ref.index
}
