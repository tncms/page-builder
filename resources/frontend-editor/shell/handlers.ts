// Phase 16 — Pure mapping from iframe -> shell messages to editor-store actions.
//
// Kept free of Vue/Pinia specifics so it can be unit-tested with a plain stub
// store. The iframe canvas component wires the real store + bridge hooks in.

import type { BridgeMessage } from '../bridge/messages'

export interface ShellStoreLike {
  selectComponent(id: string | null): void
  hoverComponent(id: string | null): void
}

export interface ShellHandlerHooks {
  onReady?: () => void
  onGeometryUpdate?: () => void
  onContextMenu?: (id: string | null, x: number, y: number) => void
  // Phase 25.3.1 §7 — the empty-column "+ Add Element" affordance was clicked;
  // the column `id` is already selected, so the shell can open the Elements tab.
  onAddElement?: (id: string | null) => void
  // Phase 26.2 — a visual column resize was committed in the iframe; apply exactly
  // one apply-column-layout command (one undo entry) for the container.
  onResizeCommit?: (containerId: string, widths: string[]) => void
}

/** Read a `{ containerId, widths }` payload from an unknown resize-commit message. */
export function resizeCommitPayload(
  payload: unknown,
): { containerId: string; widths: string[] } | null {
  if (typeof payload !== 'object' || payload === null) return null
  const record = payload as Record<string, unknown>
  const containerId = record.containerId
  const widths = record.widths
  if (typeof containerId !== 'string' || containerId === '') return null
  if (!Array.isArray(widths) || !widths.every((w) => typeof w === 'string')) return null
  return { containerId, widths: widths as string[] }
}

/** Read a string `id` from an unknown message payload, or null. */
export function payloadId(payload: unknown): string | null {
  if (typeof payload === 'object' && payload !== null && 'id' in payload) {
    const id = (payload as { id: unknown }).id
    return typeof id === 'string' ? id : null
  }
  return null
}

function coord(payload: unknown, key: 'x' | 'y'): number {
  if (typeof payload === 'object' && payload !== null && key in payload) {
    const value = (payload as Record<string, unknown>)[key]
    return typeof value === 'number' ? value : 0
  }
  return 0
}

/**
 * Dispatch a single validated iframe message against the store. Unknown types
 * never reach here (the bridge filters them), but the switch stays exhaustive
 * and side-effect-free for anything it doesn't explicitly handle.
 */
export function handleIframeMessage(
  store: ShellStoreLike,
  message: BridgeMessage,
  hooks: ShellHandlerHooks = {},
): void {
  switch (message.type) {
    case 'pb:ready':
      hooks.onReady?.()
      break

    case 'pb:component-click':
      store.selectComponent(payloadId(message.payload))
      break

    case 'pb:add-element': {
      const id = payloadId(message.payload)
      store.selectComponent(id)
      hooks.onAddElement?.(id)
      break
    }

    case 'pb:component-hover':
      store.hoverComponent(payloadId(message.payload))
      break

    case 'pb:component-contextmenu': {
      const id = payloadId(message.payload)
      store.selectComponent(id)
      hooks.onContextMenu?.(id, coord(message.payload, 'x'), coord(message.payload, 'y'))
      break
    }

    case 'pb:geometry-update':
      hooks.onGeometryUpdate?.()
      break

    case 'pb:resize-commit': {
      const parsed = resizeCommitPayload(message.payload)
      if (parsed) hooks.onResizeCommit?.(parsed.containerId, parsed.widths)
      break
    }

    case 'pb:request-command':
    default:
      break
  }
}
