// Phase 22.2 — cross-mode navigation helpers (pure, shared by both editors).
//
// Switching between the Structure editor and the Live Builder is a full page
// navigation. These helpers keep the two concerns testable and identical on both
// sides: carrying the current selection across in the query string, and guarding
// a navigation when the editor has unsaved changes.

export const DISCARD_MESSAGE = 'You have unsaved changes. Continue without saving?'

/** Append `?selected=<id>` (or `&selected=`) to a URL, preserving existing query. */
export function appendSelection(url: string | null, selectedId: string | null): string | null {
  if (!url || !selectedId) return url
  const sep = url.includes('?') ? '&' : '?'
  return `${url}${sep}selected=${encodeURIComponent(selectedId)}`
}

export interface GuardOptions {
  dirty: boolean
  /** Confirm callback (defaults to window.confirm). Return false to cancel. */
  confirm?: (message: string) => boolean
  /** Navigate callback (defaults to window.location.assign). */
  navigate?: (url: string) => void
  message?: string
}

/**
 * Navigate to `url`, first confirming when the editor is dirty. Returns whether
 * navigation happened (false when there is no url, or the user cancelled). Pure
 * apart from the injectable confirm/navigate side-effects (defaulted to the
 * browser), so the guard logic is unit-testable.
 */
export function guardedNavigate(url: string | null, opts: GuardOptions): boolean {
  if (!url) return false

  const confirmFn = opts.confirm ?? ((m) => (typeof window !== 'undefined' ? window.confirm(m) : true))
  const navigate =
    opts.navigate ??
    ((u) => {
      if (typeof window !== 'undefined') window.location.assign(u)
    })

  if (opts.dirty && !confirmFn(opts.message ?? DISCARD_MESSAGE)) return false

  navigate(url)
  return true
}
