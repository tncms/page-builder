// Phase 26.2 — Visual column resize geometry (pure, framework-free).
//
// All width math for the resize interaction lives here so it can be unit-tested
// without a DOM. Widths are PERCENTAGES that always sum to exactly 100 (no
// accumulated rounding drift). A "divider" `index` sits between column `index`
// and column `index + 1`; dragging it re-proportions ONLY that adjacent pair
// (Part 11), unless ALT mirrors the change symmetrically across the row (Part 8).

export interface ResizeConstraints {
  /** Minimum width any single column may shrink to (Part 6). */
  minPercent: number
  /** Snap grid; 0 disables snapping (Shift, Part 5). */
  snapPercent: number
}

export const DEFAULT_CONSTRAINTS: ResizeConstraints = {
  minPercent: 10,
  snapPercent: 5,
}

export interface DragOptions {
  minPercent: number
  /** 0 disables snapping. */
  snapPercent: number
  /** Symmetric resize across the row centre (ALT). */
  alt: boolean
}

/** Round to 4 decimal places, absorbing binary-float noise. */
export function round4(n: number): number {
  return Math.round(n * 10000) / 10000
}

/**
 * Force `values` (assumed to already sum to ~100) to sum to EXACTLY 100 at 4dp,
 * pushing any residual into the largest column so no column ends up odd.
 */
function fixRounding(values: number[]): number[] {
  const rounded = values.map(round4)
  if (rounded.length === 0) return rounded
  const total = round4(rounded.reduce((a, b) => a + b, 0))
  const drift = round4(100 - total)
  if (drift !== 0) {
    let idx = 0
    for (let i = 1; i < rounded.length; i += 1) {
      if (rounded[i] > rounded[idx]) idx = i
    }
    rounded[idx] = round4(rounded[idx] + drift)
  }
  return rounded
}

/**
 * Normalise arbitrary positive weights (percentages or raw pixel widths) to
 * percentages summing to exactly 100. Non-positive / non-finite weights count as
 * zero; an all-zero input falls back to an equal split.
 */
export function normalize(widths: number[]): number[] {
  const n = widths.length
  if (n === 0) return []
  const clean = widths.map((w) => (Number.isFinite(w) && w > 0 ? w : 0))
  const sum = clean.reduce((a, b) => a + b, 0)
  if (sum <= 0) return equalize(n)
  return fixRounding(clean.map((w) => (w / sum) * 100))
}

/** `n` equal columns summing to exactly 100 (Part 7 double-click equalise). */
export function equalize(n: number): number[] {
  if (n <= 0) return []
  return fixRounding(new Array(n).fill(100 / n))
}

/** Prefix sum of widths[0..index] inclusive — the divider's absolute position. */
function dividerPosition(widths: number[], index: number): number {
  let pos = 0
  for (let i = 0; i <= index; i += 1) pos += widths[i]
  return pos
}

/**
 * The feasible, snapped delta to apply to divider `index`. Snapping aligns the
 * divider's ABSOLUTE position to the grid (so the whole row shares one grid),
 * then the min-width constraints of every touched pair clamp it — min-width
 * always wins over snap, matching professional builders.
 */
function resolveDelta(
  widths: number[],
  index: number,
  deltaPercent: number,
  opts: DragOptions,
): number {
  const n = widths.length
  let d = deltaPercent
  if (opts.snapPercent > 0) {
    const pos = dividerPosition(widths, index)
    const snapped = Math.round((pos + deltaPercent) / opts.snapPercent) * opts.snapPercent
    d = snapped - pos
  }

  const min = Math.max(0, opts.minPercent)
  let dMin = min - widths[index]
  let dMax = widths[index + 1] - min

  if (opts.alt) {
    const m = n - 2 - index
    if (m >= 0 && m !== index) {
      // Mirror pair: widths[m] -= d, widths[m + 1] += d.
      dMin = Math.max(dMin, min - widths[m + 1])
      dMax = Math.min(dMax, widths[m] - min)
    }
  }

  if (dMax < dMin) return 0
  return Math.min(Math.max(d, dMin), dMax)
}

/**
 * Move divider `index` by `deltaPercent`, returning the full, normalised width
 * array. Only the adjacent pair changes (Part 11); with `alt`, the mirror pair
 * across the row centre changes symmetrically (Part 8, e.g. 30|40|30 → 25|50|25).
 * Min-width (Part 6) and snap (Part 5) are honoured; the result always sums 100.
 */
export function applyDelta(
  start: number[],
  index: number,
  deltaPercent: number,
  opts: DragOptions,
): number[] {
  const n = start.length
  if (n < 2 || index < 0 || index >= n - 1) return normalize(start)

  const widths = start.slice()
  const d = resolveDelta(widths, index, deltaPercent, opts)

  widths[index] += d
  widths[index + 1] -= d

  if (opts.alt) {
    const m = n - 2 - index
    if (m >= 0 && m !== index) {
      widths[m] -= d
      widths[m + 1] += d
    }
  }

  return normalize(widths)
}

/**
 * Serialise widths to the string tokens the layout stores (ColumnWidth accepts
 * "30", "33.3333"). Trailing-zero trimming falls out of Number → String.
 */
export function toWidthStrings(widths: number[]): string[] {
  return widths.map((w) => String(round4(w)))
}
