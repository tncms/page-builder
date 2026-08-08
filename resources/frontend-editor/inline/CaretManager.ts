// Phase 18 — Caret + auto-resize helpers for the overlay input (pure DOM utils).
//
// The overlay is a real <input>/<textarea>, so the caret is native — these are
// small utilities to place it deliberately (e.g. at the end when an edit opens),
// preserve it across programmatic value updates, and grow a textarea to fit its
// content without scrollbars.

export type TextControl = HTMLInputElement | HTMLTextAreaElement

/** Current caret as [start, end]. */
export function getCaret(el: TextControl): [number, number] {
  return [el.selectionStart ?? 0, el.selectionEnd ?? 0]
}

/** Place the caret at [start, end] (end defaults to start = collapsed caret). */
export function setCaret(el: TextControl, start: number, end: number = start): void {
  const max = el.value.length
  const s = clamp(start, 0, max)
  const e = clamp(end, 0, max)
  try {
    el.setSelectionRange(s, e)
  } catch {
    // Some input types don't support selection ranges; ignore.
  }
}

export function placeCaretAtEnd(el: TextControl): void {
  const end = el.value.length
  setCaret(el, end, end)
}

/**
 * Run `mutate` (which changes el.value) while preserving the caret position,
 * clamped to the new length. Returns nothing; the element is updated in place.
 */
export function preserveCaret(el: TextControl, mutate: () => void): void {
  const [start, end] = getCaret(el)
  mutate()
  setCaret(el, start, end)
}

/**
 * Grow a textarea to fit its content (no inner scrollbar). Resets height to auto
 * first so it can also shrink. Returns the resulting scrollHeight.
 */
export function autoResize(el: HTMLTextAreaElement): number {
  el.style.height = 'auto'
  el.style.overflowY = 'hidden'
  el.style.height = `${el.scrollHeight}px`
  return el.scrollHeight
}

function clamp(value: number, min: number, max: number): number {
  return Math.max(min, Math.min(max, value))
}
