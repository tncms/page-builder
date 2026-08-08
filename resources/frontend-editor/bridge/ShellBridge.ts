// Phase 16 — Shell (parent) side of the postMessage bridge.
//
// Sends SHELL_TO_IFRAME messages into the iframe's content window and receives
// validated IFRAME_TO_SHELL messages. Origin, source and type are all checked
// before a message reaches the handler; unknown / cross-origin / malformed
// messages are dropped silently.

import {
  BRIDGE_SOURCE,
  IFRAME_TO_SHELL,
  isAllowedOrigin,
  parseBridgeMessage,
  type BridgeMessage,
  type ShellToIframeType,
} from './messages'

export type ShellMessageHandler = (message: BridgeMessage) => void

export class ShellBridge {
  private readonly listener = (event: MessageEvent) => this.receive(event)

  private started = false

  constructor(
    private readonly iframe: Pick<HTMLIFrameElement, 'contentWindow'>,
    private readonly targetOrigin: string,
    private readonly onMessage: ShellMessageHandler,
  ) {}

  start(): void {
    if (this.started) return
    window.addEventListener('message', this.listener)
    this.started = true
  }

  stop(): void {
    if (!this.started) return
    window.removeEventListener('message', this.listener)
    this.started = false
  }

  /** Post a typed message to the iframe (no-op if it isn't mounted yet). */
  send(type: ShellToIframeType, payload?: unknown): void {
    const target = this.iframe.contentWindow
    if (!target) return
    target.postMessage({ source: BRIDGE_SOURCE, type, payload }, this.targetOrigin)
  }

  /** Exposed for unit tests: validate + dispatch a single MessageEvent. */
  receive(event: MessageEvent): void {
    if (!isAllowedOrigin(event.origin, this.targetOrigin)) return

    // Defence in depth: when we can identify the iframe window, only accept
    // messages that actually came from it. Tolerant when either side is
    // unavailable (e.g. contentWindow not yet set, or a synthetic test event
    // without a source) so validation never silently blackholes real messages.
    const expectedSource = this.iframe.contentWindow
    if (expectedSource && event.source && event.source !== expectedSource) return

    const message = parseBridgeMessage(event.data, IFRAME_TO_SHELL)
    if (!message) return

    this.onMessage(message)
  }
}
