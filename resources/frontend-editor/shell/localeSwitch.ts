// LT-M1B Increment 2B — in-editor locale-switch logic (pure, shared + testable).
//
// Switching the editor to another locale is a FULL editor navigation to the
// server-generated target URL (never an in-place hot-swap of two documents). The
// decision + the save-and-switch orchestration are kept here, framework-free, so
// the component stays a thin view and the unsaved-change semantics are unit-tested.

import type { TranslationLocaleOption } from '../../editor/types'

export type { TranslationLocaleOption }

export type LocaleSwitchDecision =
  | 'noop' // same locale, or no target URL — do nothing
  | 'navigate' // clean editor — navigate straight to the target
  | 'confirm' // dirty editor — the unsaved-change dialog must resolve first

/**
 * Decide what selecting `target` should do, given the current locale and whether
 * the editor has unsaved changes. Pure — the caller performs the effect.
 */
export function decideLocaleSwitch(
  target: TranslationLocaleOption,
  currentLocale: string,
  dirty: boolean,
): LocaleSwitchDecision {
  if (!target.editor_url || target.locale === currentLocale) {
    return 'noop'
  }

  return dirty ? 'confirm' : 'navigate'
}

export interface SaveAndSwitchIo {
  /** The editor's existing draft-save pipeline (store.saveDraft). Awaited once. */
  save: () => Promise<void>
  /** Re-checked AFTER save: true when the editor is still dirty (save failed). */
  stillDirty: () => boolean
  /** Full editor navigation to the target URL (defaults to window.location.assign). */
  navigate?: (url: string) => void
}

/**
 * Save the current locale's draft, then navigate to the target editor URL — but
 * only if the save succeeded. On a failed save the editor stays put (still dirty)
 * and the caller surfaces the existing typed save error. Returns whether it
 * navigated.
 */
export async function saveAndSwitch(url: string, io: SaveAndSwitchIo): Promise<boolean> {
  await io.save()

  // A failed save leaves the editor dirty (store.saveDraft never resets `dirty`
  // on an aborted/failed save) — never switch after a failed save.
  if (io.stillDirty()) {
    return false
  }

  navigateTo(url, io.navigate)

  return true
}

/**
 * Discard the unsaved client state and navigate. A full navigation reloads the
 * editor for the target locale, so the in-memory unsaved edits are dropped without
 * a save; the source locale's persisted draft is never mutated.
 */
export function switchWithoutSaving(url: string, navigate?: (url: string) => void): void {
  navigateTo(url, navigate)
}

function navigateTo(url: string, navigate?: (url: string) => void): void {
  if (navigate) {
    navigate(url)
    return
  }

  if (typeof window !== 'undefined') {
    window.location.assign(url)
  }
}
