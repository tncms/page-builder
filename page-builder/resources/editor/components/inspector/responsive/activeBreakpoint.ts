// Phase 26.6 — the active editing breakpoint (shared, editor-global).
//
// Like Elementor/Bricks, a single breakpoint is "active" across the whole inspector:
// every responsive control edits and previews that breakpoint together. Kept as a
// module-level ref (not the store) so it is shared, testable, and importable by the
// live-preview path without coupling to BuilderState. Ids match `breakpoints.ts` and
// the frontend shell device switcher, so a future device-preview integration can
// bind the two with no data change.

import { readonly, ref, type DeepReadonly, type Ref } from 'vue'
import { DEFAULT_BREAKPOINT } from './breakpoints'

const active = ref<string>(DEFAULT_BREAKPOINT)

/** The active editing breakpoint id (read-only to callers). */
export const activeBreakpoint: DeepReadonly<Ref<string>> = readonly(active)

/** Set the active editing breakpoint. */
export function setActiveBreakpoint(id: string): void {
  active.value = id
}
