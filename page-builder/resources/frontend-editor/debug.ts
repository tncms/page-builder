// Phase 26.3.13 — opt-in debug gate for Live Builder runtime traces.
//
// Browser-only failures can't be diagnosed from DEV-only traces because the
// served bundle is a PRODUCTION build (import.meta.env.DEV === false strips
// them). This gate is ON in dev builds AND whenever a page sets
// `window.__PB_DEBUG__ = true`, so the deployed bundle can be traced live in a
// real browser (open the console, run `window.__PB_DEBUG__ = true`, reproduce).
// Off by default in production — zero output until explicitly enabled.

export function pbDebugEnabled(): boolean {
  if (import.meta.env.DEV) return true
  const here = globalThis as { __PB_DEBUG__?: boolean; parent?: { __PB_DEBUG__?: boolean } }
  if (here.__PB_DEBUG__ === true) return true
  // The preview iframe is a separate (same-origin) window, so honor the flag set
  // on the parent shell too — one `window.__PB_DEBUG__ = true` in the shell
  // console then enables BOTH shell and iframe traces. Guarded for cross-origin.
  try {
    if (here.parent && here.parent !== here && here.parent.__PB_DEBUG__ === true) return true
  } catch {
    // cross-origin parent — not reachable, ignore.
  }
  return false
}

/** Emit a tagged trace when debugging is enabled. Build `data` lazily at call
 *  sites (guard expensive work with `pbDebugEnabled()` first) since this check
 *  is a runtime flag, not a compile-time constant. */
export function pbDebug(tag: string, data: unknown): void {
  if (!pbDebugEnabled()) return
  // eslint-disable-next-line no-console
  console.debug(tag, data)
}
