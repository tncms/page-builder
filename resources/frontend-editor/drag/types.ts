// Phase 22.1 — Cross-iframe drag: shell-side types (pure). Mirror of the iframe
// resolution shape, plus the ghost/subject the shell renders and commits.

export type DropEdge = 'before' | 'after' | 'inside'

export interface DragRect {
  top: number
  left: number
  width: number
  height: number
}

/** The dragged component as the shell tracks it (with display + payload data). */
export interface LiveDragSubject {
  id: string
  kind: string
  type: string
  label: string
  icon: string | null
  parentId: string | null
  index: number
  /** 'palette' when inserting a brand-new component (not yet in the payload). */
  source?: 'palette'
  /** The component payload to insert, when `source === 'palette'`. */
  component?: Record<string, unknown>
}

/** A resolved drop reported by the iframe (markerRect in iframe-viewport coords). */
export interface LiveDropResolution {
  valid: boolean
  reason: string | null
  edge: DropEdge | null
  targetId: string | null
  referenceId: string | null
  parentId: string | null
  markerRect: DragRect | null
}

/** The pointer-following ghost, in shell-window client coordinates. */
export interface LiveGhost {
  x: number
  y: number
}
