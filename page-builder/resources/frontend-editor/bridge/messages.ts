// Phase 16 — Frontend editor postMessage protocol (shared, pure).
//
// Both sides of the shell <-> iframe bridge agree on this small, closed message
// vocabulary. Every message is a plain object tagged with a fixed `source` and a
// `type` drawn from one of the two allow-lists below. Anything that fails
// validation is ignored — never eval'd, never trusted. There is no dynamic
// dispatch on message content beyond the known `type` switch each side owns.

export const BRIDGE_SOURCE = 'pb-frontend-editor' as const

// Bridge protocol version. Bumped whenever the shell <-> iframe contract changes
// in a way that requires BOTH bundles to be rebuilt together. It rides inside the
// readiness handshake (`pb:handshake` / `pb:ready`) so that a stale cached bundle
// on either side is DETECTED and surfaced as "assets out of date" instead of
// silently hanging on a 9s timeout. This is the safety net behind the filemtime
// cache-busting on the PHP side: even if a browser serves a stale bundle, the
// mismatch is now visible and actionable.
export const PB_BRIDGE_PROTOCOL_VERSION = 1 as const

// Shell (parent, admin panel) -> iframe (public page).
export const SHELL_TO_IFRAME = [
  'pb:load-payload',
  'pb:update-payload',
  'pb:select-component',
  'pb:hover-component',
  'pb:refresh',
  'pb:set-device',
  'pb:scroll-to-component',
  // Phase 17 — Live DOM patch (pre-commit inspector edits).
  'pb:patch-style',
  'pb:patch-text',
  'pb:patch-attr',
  'pb:clear-patches',
  // Phase 18 — Inline editing: end a session (restore the underlying element).
  'pb:inline-end',
  // Phase 21 — Live Component Runtime: replace ONE component's marker in-place
  // with freshly server-rendered HTML (no whole-canvas swap).
  'pb:replace-component',
  // Phase 22.1 — Cross-iframe drag: the shell starts/stops a drag session in the
  // iframe (which then owns the in-iframe pointer lifecycle) and echoes the
  // resolved target so the iframe can highlight it.
  'pb:drag-start',
  'pb:drag-cancel',
  'pb:drag-end',
  'pb:drag-highlight-target',
  // Hotfix — explicit readiness handshake. The shell requests readiness after the
  // iframe's `load` event, so a `pb:ready` the iframe emitted during its own boot
  // (before the shell listener was attached) is not lost forever.
  'pb:handshake',
] as const

// Iframe (public page) -> shell (parent).
export const IFRAME_TO_SHELL = [
  'pb:ready',
  'pb:component-click',
  'pb:component-hover',
  'pb:component-contextmenu',
  'pb:geometry-update',
  'pb:request-command',
  // Phase 18 — Inline editing: begin a session / report the live element rect.
  'pb:inline-start',
  'pb:inline-rect',
  // Phase 19 — Box model: report the selected element's measured box metrics.
  'pb:box-model',
  // Phase 21 — Live Component Runtime: acknowledge a component replacement so the
  // shell can restore geometry (ok) or fall back to a full refresh (ok = false).
  'pb:component-replaced',
  // Phase 22 — In-canvas toolbar: report the selected component's rect (iframe-
  // relative) so the shell can float the toolbar beside it.
  'pb:component-geometry',
  // Phase 22.1 — Cross-iframe drag: the iframe reports the pointer + resolved drop
  // target while a drag crosses it, and the terminal drop / cancel.
  'pb:drag-pointer',
  'pb:drag-target',
  'pb:drag-leave',
  'pb:drag-drop',
  'pb:drag-cancelled',
  // Hotfix — the iframe confirms it applied the initial pushed payload. Used as an
  // additional readiness signal so loading can never hang after a successful push.
  'pb:rendered',
  // Phase 25.3.1 §7 — the empty-column "+ Add Element" affordance: select the
  // column (component-click also fires) and ask the shell to open the Elements
  // tab so the next palette insert lands inside this column.
  'pb:add-element',
  // Phase 26.2 — Visual column resize: the iframe committed a divider drag; the
  // shell applies exactly one apply-column-layout command (one undo entry).
  'pb:resize-commit',
] as const

export type ShellToIframeType = (typeof SHELL_TO_IFRAME)[number]
export type IframeToShellType = (typeof IFRAME_TO_SHELL)[number]
export type BridgeMessageType = ShellToIframeType | IframeToShellType

export interface BridgeMessage<T = unknown> {
  source: typeof BRIDGE_SOURCE
  type: BridgeMessageType
  payload?: T
}

/**
 * Validate an untrusted `MessageEvent.data`. Returns the narrowed message only
 * when it is a plain object tagged with our source and a `type` that appears in
 * `allowed`; otherwise null (the caller ignores it). No content beyond `type` is
 * inspected here — payloads are validated per-handler.
 */
export function parseBridgeMessage(
  data: unknown,
  allowed: readonly string[],
): BridgeMessage | null {
  if (typeof data !== 'object' || data === null) {
    return null
  }

  const candidate = data as Record<string, unknown>

  if (candidate.source !== BRIDGE_SOURCE) {
    return null
  }

  if (typeof candidate.type !== 'string' || !allowed.includes(candidate.type)) {
    return null
  }

  return {
    source: BRIDGE_SOURCE,
    type: candidate.type as BridgeMessageType,
    payload: candidate.payload,
  }
}

/** True when `origin` matches the expected origin. `'*'` disables the check. */
export function isAllowedOrigin(origin: string, expected: string): boolean {
  return expected === '*' || origin === expected
}
