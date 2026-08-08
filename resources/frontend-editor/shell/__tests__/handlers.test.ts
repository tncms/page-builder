import { describe, it, expect, vi } from 'vitest'
import { handleIframeMessage, payloadId, type ShellStoreLike } from '../handlers'
import { BRIDGE_SOURCE, type BridgeMessage } from '../../bridge/messages'

function msg(type: string, payload?: unknown): BridgeMessage {
  return { source: BRIDGE_SOURCE, type: type as BridgeMessage['type'], payload }
}

function stubStore(): ShellStoreLike & { selectComponent: ReturnType<typeof vi.fn>; hoverComponent: ReturnType<typeof vi.fn> } {
  return {
    selectComponent: vi.fn(),
    hoverComponent: vi.fn(),
  }
}

describe('handleIframeMessage', () => {
  it('selects the component on a component-click message', () => {
    const store = stubStore()
    handleIframeMessage(store, msg('pb:component-click', { id: 'sec-1' }))
    expect(store.selectComponent).toHaveBeenCalledWith('sec-1')
  })

  it('hovers the component on a component-hover message', () => {
    const store = stubStore()
    handleIframeMessage(store, msg('pb:component-hover', { id: 'blk-2' }))
    expect(store.hoverComponent).toHaveBeenCalledWith('blk-2')
  })

  it('clears hover when payload has no id', () => {
    const store = stubStore()
    handleIframeMessage(store, msg('pb:component-hover', { id: null }))
    expect(store.hoverComponent).toHaveBeenCalledWith(null)
  })

  it('fires the ready hook and the context-menu hook', () => {
    const store = stubStore()
    const onReady = vi.fn()
    const onContextMenu = vi.fn()

    handleIframeMessage(store, msg('pb:ready', {}), { onReady, onContextMenu })
    handleIframeMessage(store, msg('pb:component-contextmenu', { id: 'sec-3', x: 10, y: 20 }), {
      onReady,
      onContextMenu,
    })

    expect(onReady).toHaveBeenCalledTimes(1)
    expect(store.selectComponent).toHaveBeenCalledWith('sec-3')
    expect(onContextMenu).toHaveBeenCalledWith('sec-3', 10, 20)
  })

  it('selects the column and fires the add-element hook (Phase 25.3.1 §7)', () => {
    const store = stubStore()
    const onAddElement = vi.fn()

    handleIframeMessage(store, msg('pb:add-element', { id: 'col-1' }), { onAddElement })

    expect(store.selectComponent).toHaveBeenCalledWith('col-1')
    expect(onAddElement).toHaveBeenCalledWith('col-1')
  })

  it('routes a resize-commit message to the onResizeCommit hook (Phase 26.2)', () => {
    const store = stubStore()
    const onResizeCommit = vi.fn()

    handleIframeMessage(store, msg('pb:resize-commit', { containerId: 'cont', widths: ['30', '70'] }), {
      onResizeCommit,
    })
    expect(onResizeCommit).toHaveBeenCalledWith('cont', ['30', '70'])
  })

  it('ignores a malformed resize-commit payload', () => {
    const store = stubStore()
    const onResizeCommit = vi.fn()

    handleIframeMessage(store, msg('pb:resize-commit', { containerId: '', widths: 'nope' }), {
      onResizeCommit,
    })
    handleIframeMessage(store, msg('pb:resize-commit', { widths: [1, 2] }), { onResizeCommit })
    expect(onResizeCommit).not.toHaveBeenCalled()
  })

  it('does nothing for messages it does not handle', () => {
    const store = stubStore()
    handleIframeMessage(store, msg('pb:request-command', { command: 'x' }))
    handleIframeMessage(store, msg('pb:geometry-update', {}))
    expect(store.selectComponent).not.toHaveBeenCalled()
    expect(store.hoverComponent).not.toHaveBeenCalled()
  })
})

describe('payloadId', () => {
  it('reads a string id or returns null', () => {
    expect(payloadId({ id: 'a' })).toBe('a')
    expect(payloadId({ id: 123 })).toBeNull()
    expect(payloadId({})).toBeNull()
    expect(payloadId(null)).toBeNull()
  })
})
