// Phase 26.5 — Media picker seam.
//
// The Vue editor runs as a standalone SPA and has no built-in bridge to the CMS
// Media Library. Rather than hard-wire a modal (which would require touching CMS
// Core — forbidden this phase), the Media editor asks THIS seam for a picker. A
// host (a future in-plugin bridge, a theme, or a test) registers one with
// `registerMediaPicker`; until then the editor falls back to a manual id/URL input.
//
// This keeps "reuse the existing Media Manager" a one-line wiring change later with
// zero rework in the editor, and keeps the editor fully testable in isolation.

/** What the editor tells the picker about the field being edited. */
export interface MediaPickerRequest {
  /** Accepted MIME/type hints from the field's `editor.accept`. */
  accept?: string[]
  /** Whether multiple selection is allowed (`editor.multiple`). */
  multiple?: boolean
  /** The currently-selected media id/URL, so the picker can pre-highlight it. */
  current?: string
}

/** What the picker returns. `null` means the user cancelled. */
export interface MediaPickerResult {
  /** The chosen media id or URL. */
  src: string
  /** Optional alt text, used only when the field opts into alt (`editor.alt`). */
  alt?: string
}

export type MediaPicker = (request: MediaPickerRequest) => Promise<MediaPickerResult | null>

let picker: MediaPicker | null = null

/** Register (or clear, with `null`) the host media picker. Last registration wins. */
export function registerMediaPicker(next: MediaPicker | null): void {
  picker = next
}

/** The registered picker, or null when the manual-input fallback should be used. */
export function resolveMediaPicker(): MediaPicker | null {
  return picker
}

/** Whether a host picker is available. */
export function hasMediaPicker(): boolean {
  return picker !== null
}
