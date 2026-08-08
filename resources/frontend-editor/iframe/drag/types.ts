// Phase 22.1 — Cross-iframe drag: iframe-side types (pure).
//
// The iframe owns the pointer lifecycle once the shell hands off a drag. It
// resolves the drop purely from the rendered markers + a container-capability
// set the shell supplies (the DOM alone can't say which blocks are containers).
// It never mutates the layout — it only reports intent back to the shell.

export type DropEdge = 'before' | 'after' | 'inside'

/** The dragged component, as handed over by the shell on `pb:drag-start`. */
export interface DragSubject {
  id: string
  kind: string // 'section' | 'block' | 'container'
  type: string
  parentId: string | null
  index: number
  /** 'palette' when inserting a brand-new component (not yet in the payload). */
  source?: 'palette'
}

/** Rectangle in the iframe's own viewport coordinates (getBoundingClientRect). */
export interface IframeRect {
  top: number
  left: number
  width: number
  height: number
}

/**
 * A resolved drop, reported to the shell. `referenceId` is the marker the
 * insertion line / box is drawn against; `parentId` is the container the subject
 * would land in (null = root); `markerRect` is the reference marker's rect in
 * iframe-viewport coordinates (the shell adds the iframe screen offset).
 */
export interface IframeDropResolution {
  valid: boolean
  reason: string | null
  edge: DropEdge | null
  targetId: string | null
  referenceId: string | null
  parentId: string | null
  markerRect: IframeRect | null
}

/** Context the resolver needs beyond the DOM: which ids are containers. */
export interface DropContext {
  /** Ids of every section + container block (a block DOM node can't say). */
  isContainer: (id: string) => boolean
}

export function invalidResolution(reason: string): IframeDropResolution {
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
