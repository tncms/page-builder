// Phase 26.5 — recent colors for the Color editor.
//
// A tiny module-level reactive list so every ColorField instance shares the same
// palette of recently-committed colors within an editing session. Deliberately
// in-memory (no persistence) — a session-scoped convenience, not stored data. Kept
// out of the component so it is independently testable and reusable by other editors
// (e.g. Border/Background colors) later.

import { readonly, ref, type DeepReadonly, type Ref } from 'vue'

const MAX = 8

const list = ref<string[]>([])

/** The shared recent-color list (read-only to callers). */
export const recentColors: DeepReadonly<Ref<string[]>> = readonly(list)

/**
 * Record a color as recently used. Most-recent first, de-duplicated, capped at MAX.
 * Empty/whitespace values are ignored. Pure aside from the module ref it owns.
 */
export function pushRecentColor(color: string): void {
  const c = color.trim()
  if (c === '') return
  list.value = [c, ...list.value.filter((x) => x !== c)].slice(0, MAX)
}

/** Clear the shared list (used by tests to isolate state). */
export function clearRecentColors(): void {
  list.value = []
}
