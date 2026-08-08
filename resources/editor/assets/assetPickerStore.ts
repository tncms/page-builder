// Phase 26.20 — Asset Source Picker store (imperative Promise dialog).
//
// Bridges the callback-shaped `MediaPicker` seam ((req) => Promise<MediaPickerResult|null>) to
// a Vue dialog: `openPicker` returns a promise that resolves when the user confirms (with the
// existing `{ src, alt? }` result) or cancels (null). The pending resolver is kept OUTSIDE
// reactive state (a plain function is not reactive data).
//
// Confirm gates on URL safety — an empty or unsafe draft can never be committed, mirroring the
// server renderer's `Html::safeUrl` policy. The store never mutates component data or dispatches
// a command; committing happens on the MediaField side through the normal Apply/undo path.

import { defineStore } from 'pinia'
import type { MediaPickerRequest, MediaPickerResult } from '../components/inspector/mediaPicker'
import { toMediaResult, type AssetReference } from './contracts/AssetReference'
import { resolveAssetPreview } from './runtime/resolveAssetPreview'

/** Which source tab is active. */
export type AssetPickerSource = 'library' | 'url'

interface State {
  open: boolean
  source: AssetPickerSource
  request: MediaPickerRequest | null
  draft: AssetReference | null
  alt: string
}

// Non-reactive pending resolver for the in-flight openPicker() promise.
let pendingResolve: ((result: MediaPickerResult | null) => void) | null = null

export const useAssetPickerStore = defineStore('pbAssetPicker', {
  state: (): State => ({
    open: false,
    source: 'library',
    request: null,
    draft: null,
    alt: '',
  }),

  getters: {
    /** Whether the current draft can be committed (present, non-empty, safe URL). */
    canConfirm(s): boolean {
      if (!s.draft) return false
      const preview = resolveAssetPreview(s.draft)
      return !preview.empty && preview.safe
    },
  },

  actions: {
    /**
     * Open the picker for a media field request and return a promise for the result. If a
     * picker is already open, the previous request is cancelled (resolves null) first.
     */
    openPicker(request?: MediaPickerRequest): Promise<MediaPickerResult | null> {
      if (pendingResolve) {
        pendingResolve(null)
        pendingResolve = null
      }
      this.request = request ?? null
      this.draft = null
      this.alt = ''
      this.source = 'library'
      this.open = true
      return new Promise((resolve) => {
        pendingResolve = resolve
      })
    },

    setSource(source: AssetPickerSource): void {
      this.source = source
    },

    /** Set the active draft (from a source component). Seeds alt from the reference. */
    setDraft(draft: AssetReference | null): void {
      this.draft = draft
      if (draft?.alt != null) this.alt = draft.alt
    },

    setAlt(value: string): void {
      this.alt = value
    },

    /** Commit the draft: resolve the promise with the `{ src, alt? }` seam result. */
    confirm(): boolean {
      if (!this.draft) return false
      const merged: AssetReference = { ...this.draft, alt: this.alt.trim() || undefined }
      const preview = resolveAssetPreview(merged)
      if (preview.empty || !preview.safe) return false
      this.settle(toMediaResult(merged))
      return true
    },

    /** Cancel: resolve the promise with null. */
    cancel(): void {
      this.settle(null)
    },

    /** Resolve the pending promise and reset dialog state. */
    settle(result: MediaPickerResult | null): void {
      this.open = false
      this.draft = null
      this.request = null
      this.alt = ''
      const resolve = pendingResolve
      pendingResolve = null
      if (resolve) resolve(result)
    },
  },
})
