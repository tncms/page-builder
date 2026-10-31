// LT-M1B Increment 2B — in-editor locale-switch logic (pure, shared + testable).
//
// Switching the editor to another locale is a FULL editor navigation to the
// server-generated target URL (never an in-place hot-swap of two documents). The
// decision + the save-and-switch orchestration are kept here, framework-free, so
// the component stays a thin view and the unsaved-change semantics are unit-tested.

import type { TranslationLocaleOption } from '../../editor/types'

export type { TranslationLocaleOption }

export type LocaleSwitchDecision =
  | 'noop' // same locale, or nothing to open or create — do nothing
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
  if (!isOpenable(target) || target.locale === currentLocale) {
    return 'noop'
  }

  return dirty ? 'confirm' : 'navigate'
}

/** Whether selecting the option can lead anywhere: an existing editor, or a creatable translation. */
export function isOpenable(target: TranslationLocaleOption): boolean {
  return Boolean(target.editor_url) || (target.can_create && Boolean(target.create_url))
}

/** The server's refusal code when the source has no Page Builder content to copy. */
export const BLANK_DECISION_REQUIRED = 'pb_blank_layout_decision_required'

/**
 * PB-CORE-AUTHORITY-REMEDIATION-R1 — the explicit blank-layout decision, built ONLY
 * from the server's 409 answer (the server decides; the client never guesses).
 */
export interface BlankLayoutDecision {
  locale: string
  sourceLayout: string
  returnUrl: string | null
  createUrl: string
}

/** The decision carried by a refused create, or null for any other failure. */
export function blankDecisionFrom(error: unknown, createUrl: string): BlankLayoutDecision | null {
  const typed = error as { code?: unknown; current?: Record<string, unknown> | null } | null
  if (!typed || typed.code !== BLANK_DECISION_REQUIRED) {
    return null
  }

  const data = typed.current ?? {}

  return {
    locale: String(data.locale ?? ''),
    sourceLayout: String(data.source_layout ?? 'absent'),
    returnUrl: typeof data.return_url === 'string' && data.return_url !== '' ? data.return_url : null,
    createUrl,
  }
}

/** Create the blank translated layout on the explicit choice, then open it. */
export async function createBlankAndOpen(decision: BlankLayoutDecision, io: OpenLocaleIo): Promise<void> {
  const url = await io.create(decision.createUrl, decision.locale, 'blank')
  if (!url) {
    throw new Error('The translation was created but its editor is unavailable.')
  }

  navigateTo(url, io.navigate)
}

export interface OpenLocaleIo {
  /** POSTs the server `create_url`; resolves to the editor URL of the created translation. */
  create: (createUrl: string, locale: string, layout?: 'blank') => Promise<string | null>
  /** Full editor navigation (defaults to window.location.assign). */
  navigate?: (url: string) => void
}

/**
 * Open the target locale's editor. An existing translation navigates straight to
 * its server URL; a missing one is first CREATED through the server (never opened
 * by URL, which fails closed) and then opened. Rejects when creation fails, so the
 * caller can surface the error and stay put.
 */
export async function openLocaleTarget(target: TranslationLocaleOption, io: OpenLocaleIo): Promise<void> {
  if (target.editor_url) {
    navigateTo(target.editor_url, io.navigate)
    return
  }

  if (!target.can_create || !target.create_url) {
    throw new Error('This language has no translation yet.')
  }

  const url = await io.create(target.create_url, target.locale)
  if (!url) {
    throw new Error('The translation was created but its editor is unavailable.')
  }

  navigateTo(url, io.navigate)
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
 * Save the current locale's draft, then navigate to the target editor URL (or run
 * the given open step, e.g. create-then-open) — but only if the save succeeded. On
 * a failed save the editor stays put (still dirty) and the caller surfaces the
 * existing typed save error. Returns whether it switched.
 */
export async function saveAndSwitch(url: string | (() => Promise<void>), io: SaveAndSwitchIo): Promise<boolean> {
  await io.save()

  // A failed save leaves the editor dirty (store.saveDraft never resets `dirty`
  // on an aborted/failed save) — never switch after a failed save.
  if (io.stillDirty()) {
    return false
  }

  if (typeof url === 'function') {
    await url()
  } else {
    navigateTo(url, io.navigate)
  }

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
