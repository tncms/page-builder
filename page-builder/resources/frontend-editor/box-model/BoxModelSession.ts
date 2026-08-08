// Phase 19 — A single box-model drag session (pure). Tracks which side of which
// box is being dragged from what start value, and derives the live style patch +
// the committed spacing-data update.

import { clampNonNegative, sideDelta, snap } from './BoxModelCalculator'
import type { BoxSides, BoxType, Side } from './types'

const CAP: Record<Side, string> = { top: 'Top', right: 'Right', bottom: 'Bottom', left: 'Left' }

export class BoxModelSession {
  constructor(
    readonly componentId: string,
    readonly boxType: BoxType,
    readonly side: Side,
    readonly startValue: number,
  ) {}

  /** Resulting value for a pointer delta, clamped ≥ 0 and optionally snapped. */
  valueAt(dx: number, dy: number, snapTo = false): number {
    const raw = clampNonNegative(this.startValue + sideDelta(this.side, dx, dy))
    return snapTo ? snap(raw) : Math.round(raw)
  }

  /** The camelCase CSS property this session drives (e.g. paddingTop). */
  get styleProp(): string {
    return `${this.boxType}${CAP[this.side]}`
  }

  /** Live style patch for a value (Phase 17 pb:patch-style payload style map). */
  stylePatch(value: number): Record<string, string> {
    return { [this.styleProp]: `${value}px` }
  }

  /**
   * Fold a value into the flat `spacing` data map, preserving the other side/box
   * values already present.
   */
  spacingUpdate(currentSpacing: unknown, value: number): { padding: Partial<BoxSides>; margin: Partial<BoxSides> } {
    const base = normalizeSpacing(currentSpacing)
    base[this.boxType] = { ...base[this.boxType], [this.side]: value }
    return base
  }
}

function normalizeSpacing(value: unknown): { padding: Partial<BoxSides>; margin: Partial<BoxSides> } {
  const obj = value && typeof value === 'object' ? (value as Record<string, unknown>) : {}
  const side = (v: unknown): Partial<BoxSides> =>
    v && typeof v === 'object' ? { ...(v as Partial<BoxSides>) } : {}
  return { padding: side(obj.padding), margin: side(obj.margin) }
}
