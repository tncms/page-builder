// Phase 22 — In-canvas component toolbar: shared types (pure, no Vue/Pinia).
//
// The toolbar floats beside the selected component in the Live Builder. The
// iframe reports the selected component's geometry (viewport-relative rect); the
// shell translates that into a screen position via ToolbarPositioner.

export type ToolbarPlacement = 'above' | 'inside' | 'below'

export type SidebarTab = 'elements' | 'navigator' | 'options'

/** A rectangle in whatever coordinate space the caller documents (all px). */
export interface ToolbarRect {
  top: number
  left: number
  width: number
  height: number
}

/**
 * Geometry the iframe reports for the currently selected component. `rect` is
 * relative to the iframe's own viewport (getBoundingClientRect), matching the
 * inline + box-model messages; the shell adds the iframe screen offset.
 */
export interface ComponentGeometry {
  componentId: string
  rect: ToolbarRect
  parentId: string | null
  kind: string
  type: string
}

/** Inputs for the pure toolbar positioner. All rects are in px. */
export interface ToolbarPositionInput {
  /** Selected component rect, relative to the iframe viewport. */
  component: ToolbarRect
  /** The iframe element's own screen rect (getBoundingClientRect). */
  iframe: ToolbarRect
  /** Screen bounds to clamp within (usually window inner size). */
  viewport: { width: number; height: number }
  /** Measured toolbar size. */
  toolbar: { width: number; height: number }
  /** Gap between the toolbar and the component edge. Default 8. */
  gap?: number
}

export interface ToolbarPositionResult {
  top: number
  left: number
  placement: ToolbarPlacement
  clamped: boolean
}
