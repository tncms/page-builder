// PB-CORE-AUTHORITY-REMEDIATION-R1 — render-source (editor mode) switch logic (pure,
// shared + testable), mirroring localeSwitch.ts.
//
// A switch only selects which source renders publicly; the server never rewrites
// either source. The component always asks for confirmation; with unsaved changes
// the user must choose Save, Discard or Cancel explicitly. Cancel writes nothing.

export type EditorMode = 'classic' | 'builder'

/** The mode a switch goes TO from the current one (anything unknown counts as classic). */
export function targetModeFor(current: string | null | undefined): EditorMode {
  return current === 'builder' ? 'classic' : 'builder'
}

/**
 * The user's choice in the confirmation dialog:
 *  - `switch`  — clean editor: switch now
 *  - `save`    — save the draft first, switch only if the save succeeded
 *  - `discard` — drop the unsaved client state, then switch
 */
export type ModeSwitchChoice = 'switch' | 'save' | 'discard'

export interface ModeSwitchIo {
  /** The editor's existing draft-save pipeline (store.saveDraft). */
  save: () => Promise<unknown>
  /** Re-checked AFTER save: true when the editor is still dirty (save failed). */
  stillDirty: () => boolean
  /** The server switch; rejects on refusal (403/409/422) with nothing written. */
  switchMode: (target: EditorMode) => Promise<void>
  /** Reload the editor so discarded client state is dropped (defaults to location.reload). */
  reload?: () => void
}

/**
 * Run a confirmed switch. Returns whether the server switched. A failed save never
 * switches; a refused switch rejects so the caller shows the server message and the
 * editor stays exactly as it was (a discard only reloads after a successful switch).
 */
export async function runModeSwitch(choice: ModeSwitchChoice, target: EditorMode, io: ModeSwitchIo): Promise<boolean> {
  if (choice === 'save') {
    await io.save()
    if (io.stillDirty()) {
      return false
    }
  }

  await io.switchMode(target)

  if (choice === 'discard') {
    if (io.reload) {
      io.reload()
    } else if (typeof window !== 'undefined') {
      window.location.reload()
    }
  }

  return true
}
