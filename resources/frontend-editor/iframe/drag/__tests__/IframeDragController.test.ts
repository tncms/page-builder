import { describe, it, expect, afterEach, vi } from 'vitest'
import { IframeDragController } from '../IframeDragController'
import { standardCanvas, STANDARD_CONTAINERS } from './dom'
import { BRIDGE_SOURCE } from '../../../bridge/messages'
import type { BridgeMessage } from '../../../bridge/messages'

afterEach(() => {
  document.body.innerHTML = ''
})

function msg(type: string, payload?: unknown): BridgeMessage {
  return { source: BRIDGE_SOURCE, type: type as never, payload }
}

function start(controller: IframeDragController): void {
  controller.onShellMessage(
    msg('pb:drag-start', {
      subject: { id: 's1', kind: 'section', type: 'hero', parentId: null, index: 0 },
      containerIds: STANDARD_CONTAINERS,
    }),
  )
}

describe('IframeDragController lifecycle', () => {
  it('ignores pointer events until a drag starts', () => {
    const send = vi.fn()
    // eslint-disable-next-line no-new
    new IframeDragController(standardCanvas(), send)
    document.dispatchEvent(new MouseEvent('pointermove', { clientX: 250, clientY: 380 }))
    expect(send).not.toHaveBeenCalled()
  })

  it('reports pointer + target on move and drops once on pointerup', () => {
    const send = vi.fn()
    const controller = new IframeDragController(standardCanvas(), send)
    start(controller)
    expect(controller.dragging).toBe(true)

    document.dispatchEvent(new MouseEvent('pointermove', { clientX: 250, clientY: 380 }))
    const types = send.mock.calls.map((c) => c[0])
    expect(types).toContain('pb:drag-pointer')
    expect(types).toContain('pb:drag-target')

    send.mockClear()
    document.dispatchEvent(new MouseEvent('pointerup', { clientX: 250, clientY: 380 }))
    expect(send.mock.calls.filter((c) => c[0] === 'pb:drag-drop')).toHaveLength(1)
    expect(controller.dragging).toBe(false)
  })

  it('cancels on Escape', () => {
    const send = vi.fn()
    const controller = new IframeDragController(standardCanvas(), send)
    start(controller)
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    expect(send).toHaveBeenCalledWith('pb:drag-cancelled', {})
    expect(controller.dragging).toBe(false)
  })

  it('routes only its own shell messages', () => {
    const controller = new IframeDragController(standardCanvas(), vi.fn())
    expect(controller.onShellMessage(msg('pb:drag-cancel'))).toBe(true)
    expect(controller.onShellMessage(msg('pb:select-component'))).toBe(false)
  })
})
