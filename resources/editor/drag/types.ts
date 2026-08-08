// Phase 14 — Drag session types.
//
// The drag engine is preview-only: it never mutates the layout during a
// gesture. These shapes describe what a drag is *about* to do so the marker,
// ghost and store can render intent; the actual structural change happens once,
// on drop, through the Phase 8 command bus.

import type { GeometryRect } from '../geometry'

export type DropEdge = 'before' | 'after' | 'inside'

/**
 * The component being dragged, captured once at pickup. Source parent/index are
 * recorded so the drop can compensate for the remove-then-insert the backend
 * move performs (same-parent reorders shift by one).
 */
export interface DragSubject {
  id: string
  kind: string // 'section' | 'block' | 'container'
  type: string
  label: string
  icon: string | null
  parentId: string | null
  index: number
  /**
   * 'palette' when the drag originates from the Insert palette (a new component
   * that isn't in the payload yet). Undefined for moving an existing component.
   */
  source?: 'palette'
  /** The component payload to insert, when `source === 'palette'`. */
  component?: Record<string, unknown>
}

/**
 * Where the dragged subject would land, resolved purely from geometry. `parentId`
 * is the container it would drop into (null = root), `referenceId` is the
 * component the insertion marker is drawn against, and `markerRect` is that
 * component's rect in viewport-local coordinates.
 */
export interface DropResolution {
  valid: boolean
  reason: string | null
  edge: DropEdge | null
  targetId: string | null
  referenceId: string | null
  parentId: string | null
  markerRect: GeometryRect | null
}

/** The floating ghost that follows the pointer (client coordinates). */
export interface GhostState {
  label: string
  type: string
  icon: string | null
  x: number
  y: number
}

/** A drag projected into reactive store state for the Vue layer. */
export interface DragProjection {
  subject: DragSubject
  resolution: DropResolution | null
}

export function invalidResolution(reason: string): DropResolution {
  return {
    valid: false,
    reason,
    edge: null,
    targetId: null,
    referenceId: null,
    parentId: null,
    markerRect: null,
  }
}
