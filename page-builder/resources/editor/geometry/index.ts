// Phase 13 — Geometry engine singletons.
//
// The editor is a single mounted app, so one geometry engine backs the whole
// visual canvas. Overlays import these instances (never the DOM) to read rects;
// the store wires the change listener so a refresh bumps a reactive version and
// overlays recompute. Kept out of Pinia to stay non-reactive (the entries hold
// live DOM nodes, which must not be made reactive).

import { GeometryManager } from './GeometryManager'
import { OverlayManager } from './OverlayManager'

export const geometryManager = new GeometryManager()
export const overlayManager = new OverlayManager(geometryManager)

export function useGeometry(): { manager: GeometryManager; overlay: OverlayManager } {
  return { manager: geometryManager, overlay: overlayManager }
}

export type { GeometryEntry, GeometryRect } from './GeometryManager'
export type { DropTarget, EdgeHit, InsertionPoint, Edge } from './OverlayManager'
export { GeometryManager } from './GeometryManager'
export { OverlayManager } from './OverlayManager'
export { GeometryCache } from './GeometryCache'
export { GeometryObserver } from './GeometryObserver'
export { HitTest } from './HitTest'
