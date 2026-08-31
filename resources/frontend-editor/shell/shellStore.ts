// Phase 16 — Shell-only state (device + iframe readiness + boot links).
//
// The heavy editing state (payload, selection, commands, draft/publish) lives in
// the reused structure-editor store. This small store holds only what is unique
// to the frontend shell so the device toolbar and top bar stay testable in
// isolation.

import { defineStore } from 'pinia'
import { deviceWidth, isDevice, type Device } from './device'
import type { FrontendBootData } from './types'

interface ShellState {
  boot: FrontendBootData | null
  device: Device
  iframeReady: boolean
  // Hotfix — set when the readiness handshake times out, so the shell can show a
  // clear "bridge did not initialize" message + Retry instead of an infinite spinner.
  iframeTimedOut: boolean
  // Precise, same-origin-derived reason shown alongside the timeout state (script
  // never loaded vs. loaded-but-rejected vs. generic). Null falls back to the
  // generic copy.
  timeoutReason: string | null
  // Set when the iframe answers the handshake but reports a DIFFERENT bridge
  // protocol version — i.e. a stale cached bundle on one side. Retry cannot fix
  // this, so the shell tells the user to hard-refresh / clear cache instead.
  iframeProtocolMismatch: boolean
}

export const useShellStore = defineStore('pbFrontendShell', {
  state: (): ShellState => ({
    boot: null,
    device: 'desktop',
    iframeReady: false,
    iframeTimedOut: false,
    timeoutReason: null,
    iframeProtocolMismatch: false,
  }),

  getters: {
    iframeUrl: (state): string => state.boot?.iframeUrl ?? '',

    // Derive the postMessage target/validation origin from the ACTUAL iframe URL
    // (robust to http/https, www, port, or an APP_URL that differs from the
    // request host). Falls back to the server-provided value, then this origin.
    frontendOrigin: (state): string => {
      const url = state.boot?.iframeUrl
      if (url) {
        try {
          return new URL(url, window.location.href).origin
        } catch {
          // Malformed URL — fall through to the server-provided origin.
        }
      }
      return state.boot?.frontendOrigin ?? window.location.origin
    },

    publicUrl: (state): string => state.boot?.publicUrl ?? '/',
    // Whether "View Page" has a real, accessible public target. Defaults to true so
    // published owners (and older boot payloads) keep their working link; only an
    // explicit false (unpublished owner) disables the action (PB-DRAFT-PREVIEW-R1).
    publicUrlAvailable: (state): boolean => state.boot?.publicUrlAvailable ?? true,
    structureEditUrl: (state): string | null => state.boot?.structureEditUrl ?? null,
    iframeWidth: (state): string => deviceWidth(state.device),
  },

  actions: {
    bootstrap(boot: FrontendBootData) {
      this.boot = boot
    },

    setDevice(device: Device) {
      if (isDevice(device)) {
        this.device = device
      }
    },

    setIframeReady(ready: boolean) {
      this.iframeReady = ready
      if (ready) {
        this.iframeTimedOut = false
        this.iframeProtocolMismatch = false
        this.timeoutReason = null
      }
    },

    setIframeTimedOut(timedOut: boolean, reason: string | null = null) {
      this.iframeTimedOut = timedOut
      this.timeoutReason = timedOut ? reason : null
    },

    // The iframe answered but with an incompatible bridge protocol — a stale
    // cached bundle. A distinct, terminal state (Retry won't help).
    setProtocolMismatch(mismatch: boolean) {
      this.iframeProtocolMismatch = mismatch
    },

    // Reset readiness before (re)loading the iframe / retrying the handshake.
    resetIframeState() {
      this.iframeReady = false
      this.iframeTimedOut = false
      this.iframeProtocolMismatch = false
      this.timeoutReason = null
    },
  },
})
