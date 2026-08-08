// Phase 16 — Iframe (child) side of the postMessage bridge.
//
// Mirror of ShellBridge: sends IFRAME_TO_SHELL messages up to window.parent and
// receives validated SHELL_TO_IFRAME messages. Origin, source and type are all
// checked; anything else is ignored.

import {
  BRIDGE_SOURCE,
  isAllowedOrigin,
  parseBridgeMessage,
  SHELL_TO_IFRAME,
  type BridgeMessage,
  type IframeToShellType,
} from './messages'

export type IframeMessageHandler = (message: BridgeMessage) => void

export class IframeBridge {
  private readonly listener = (event: MessageEvent) => this.receive(event)

  private started = false

  constructor(
    private readonly parentWindow: Pick<Window, 'postMessage'>,
    private readonly targetOrigin: string,
    private readonly onMessage: IframeMessageHandler,
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

  /** Post a typed message up to the shell. */
  send(type: IframeToShellType, payload?: unknown): void {
    this.parentWindow.postMessage(
      { source: BRIDGE_SOURCE, type, payload },
      this.targetOrigin,
    )
  }

  /** Exposed for unit tests: validate + dispatch a single MessageEvent. */
  receive(event: MessageEvent): void {
    if (!isAllowedOrigin(event.origin, this.targetOrigin)) return

    const message = parseBridgeMessage(event.data, SHELL_TO_IFRAME)
    if (!message) return

    this.onMessage(message)
  }
}
