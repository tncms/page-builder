// Phase 19 — Box model geometry (pure). Turns measured metrics + the iframe's
// screen offset into the four nested boxes (margin > border > padding > content)
// in screen coordinates, plus per-side handle centres and the drag delta math.

import type { BoxMetrics, BoxRect, BoxType, Side } from './types'

export interface Offset {
  top: number
  left: number
}

export interface Boxes {
  margin: BoxRect
  border: BoxRect
  padding: BoxRect
  content: BoxRect
}

function inset(box: BoxRect, top: number, right: number, bottom: number, left: number): BoxRect {
  return {
    top: box.top + top,
    left: box.left + left,
    width: Math.max(0, box.width - left - right),
    height: Math.max(0, box.height - top - bottom),
  }
}

function outset(box: BoxRect, top: number, right: number, bottom: number, left: number): BoxRect {
  return {
    top: box.top - top,
    left: box.left - left,
    width: box.width + left + right,
    height: box.height + top + bottom,
  }
}

/**
 * Compute the nested boxes in SCREEN space. `metrics.rect` is the element's
 * border box in iframe-viewport coordinates; `offset` is the iframe element's
 * screen position, so screen = offset + rect.
 */
export function computeBoxes(metrics: BoxMetrics, offset: Offset): Boxes {
  const { margin, padding, border, rect } = metrics
  const borderBox: BoxRect = {
    top: offset.top + rect.top,
    left: offset.left + rect.left,
    width: rect.width,
    height: rect.height,
  }

  return {
    margin: outset(borderBox, margin.top, margin.right, margin.bottom, margin.left),
    border: borderBox,
    padding: inset(borderBox, border.top, border.right, border.bottom, border.left),
    content: inset(
      borderBox,
      border.top + padding.top,
      border.right + padding.right,
      border.bottom + padding.bottom,
      border.left + padding.left,
    ),
  }
}

/** Screen centre of a box's side edge (where the drag handle sits). */
export function handleCenter(box: BoxRect, side: Side): { x: number; y: number } {
  const midX = box.left + box.width / 2
  const midY = box.top + box.height / 2
  switch (side) {
    case 'top':
      return { x: midX, y: box.top }
    case 'bottom':
      return { x: midX, y: box.top + box.height }
    case 'left':
      return { x: box.left, y: midY }
    case 'right':
      return { x: box.left + box.width, y: midY }
  }
}

/** The box whose edge a handle of (type, side) sits on. */
export function handleBox(boxes: Boxes, type: BoxType, side: Side): BoxRect {
  // Margin handles sit on the outer (margin) edge; padding handles on the padding
  // box edge (inner side of the border box).
  return type === 'margin' ? boxes.margin : boxes.padding
}

/**
 * Pointer delta -> spacing delta for a side: dragging a handle OUTWARD increases
 * the spacing. Screen y grows downward, so top uses -dy and left uses -dx.
 */
export function sideDelta(side: Side, dx: number, dy: number): number {
  switch (side) {
    case 'top':
      return -dy
    case 'bottom':
      return dy
    case 'left':
      return -dx
    case 'right':
      return dx
  }
}

export function clampNonNegative(value: number): number {
  return value < 0 ? 0 : value
}

/** Snap to a 10px grid (used while a modifier key is held). */
export function snap(value: number, step = 10): number {
  return Math.round(value / step) * step
}
