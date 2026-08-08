// Phase 22 — Pure toolbar positioning. No DOM queries: it takes the selected
// component rect (iframe-relative), the iframe screen rect, the viewport bounds
// and the measured toolbar size, and returns a clamped screen position.
//
// Placement preference (spec §5):
//   1. Above top-left
//   2. Inside top-left (when there isn't room above but the component is tall
//      enough to host the toolbar without covering all of it)
//   3. Below top-left
//   4. Clamp inside the viewport (never render offscreen)

import type { ToolbarPositionInput, ToolbarPositionResult, ToolbarPlacement } from './types'

const DEFAULT_GAP = 8

function clamp(value: number, min: number, max: number): { value: number; clamped: boolean } {
  if (max < min) {
    // Degenerate bounds (toolbar wider/taller than viewport): pin to min.
    return { value: min, clamped: value !== min }
  }
  if (value < min) return { value: min, clamped: true }
  if (value > max) return { value: max, clamped: true }
  return { value, clamped: false }
}

export function positionToolbar(input: ToolbarPositionInput): ToolbarPositionResult {
  const gap = input.gap ?? DEFAULT_GAP

  // Selected component, translated into screen coordinates.
  const screenTop = input.iframe.top + input.component.top
  const screenLeft = input.iframe.left + input.component.left
  const componentBottom = screenTop + input.component.height

  // Prefer above; fall back to inside then below.
  let placement: ToolbarPlacement
  let desiredTop: number

  const aboveTop = screenTop - input.toolbar.height - gap
  const roomAbove = aboveTop >= 0
  // "Inside" only makes sense when the component can host the toolbar without
  // swallowing it entirely — otherwise the toolbar covers the whole component.
  const roomInside = input.component.height >= input.toolbar.height + gap * 2

  if (roomAbove) {
    placement = 'above'
    desiredTop = aboveTop
  } else if (roomInside) {
    placement = 'inside'
    desiredTop = screenTop + gap
  } else {
    placement = 'below'
    desiredTop = componentBottom + gap
  }

  const maxLeft = input.viewport.width - input.toolbar.width
  const maxTop = input.viewport.height - input.toolbar.height

  // HOTFIX — the left floor is the iframe's own left edge, not 0: the sidebar
  // occupies the shell to the left of the iframe, so clamping to 0 let the toolbar
  // slide under it. Pinning to `iframe.left` keeps the toolbar over the preview.
  const left = clamp(screenLeft, input.iframe.left, maxLeft)
  const top = clamp(desiredTop, 0, maxTop)

  return {
    top: top.value,
    left: left.value,
    placement,
    clamped: left.clamped || top.clamped,
  }
}
