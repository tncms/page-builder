// Phase 14 — Drag engine singletons.
//
// One drag engine backs the single mounted editor, built on the shared geometry
// engine (so hit-testing and rects come from the same measured source the
// overlays use). The store binds the manager to the live canvas and projects the
// session into reactive state; Vue components read that projection, never these
// instances directly. Kept out of Pinia for the same reason geometry is — the
// session holds transient, non-reactive gesture state.

import { geometryManager, overlayManager } from '../geometry'
import { AutoScroll } from './AutoScroll'
import { DragManager } from './DragManager'
import { DragSession } from './DragSession'
import { DropManager } from './DropManager'
import { OutlineDragController } from './OutlineDragController'

export const dragSession = new DragSession()
export const dropManager = new DropManager(geometryManager, overlayManager.hitTest)
export const autoScroll = new AutoScroll()
export const dragManager = new DragManager(dragSession, dropManager, autoScroll)

// Outline mode drags against explicit DOM drop zones, not measured geometry, so
// it gets its own controller (Phase 14.4). Both project into the same reactive
// store drag state and share the same command API on drop.
export const outlineDragController = new OutlineDragController()

export { AutoScroll } from './AutoScroll'
export { DragManager } from './DragManager'
export { DragSession } from './DragSession'
export { DropManager } from './DropManager'
export { OutlineDragController, domZoneAt } from './OutlineDragController'
export type { OutlineDragCallbacks, ZoneResolver } from './OutlineDragController'
export type { DragCallbacks } from './DragManager'
export type { DragSubject, DropEdge, DropResolution, DragProjection, GhostState } from './types'
export type { OutlineZone, OutlineDropTarget } from './outline'
export { resolveOutlineDrop, outlineZoneKey } from './outline'
export { invalidResolution } from './types'
