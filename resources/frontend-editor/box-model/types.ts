// Phase 19 — Box model shared types.

export type Side = 'top' | 'right' | 'bottom' | 'left'
export type BoxType = 'padding' | 'margin'

export interface BoxSides {
  top: number
  right: number
  bottom: number
  left: number
}

export interface BoxRect {
  top: number
  left: number
  width: number
  height: number
}

/** Measured metrics of a component's root element (all px, from getComputedStyle). */
export interface BoxMetrics {
  componentId: string
  margin: BoxSides
  padding: BoxSides
  border: BoxSides
  rect: BoxRect
}

export const SIDES: Side[] = ['top', 'right', 'bottom', 'left']
