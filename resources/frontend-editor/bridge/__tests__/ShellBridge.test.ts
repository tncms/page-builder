import { describe, it, expect, vi } from 'vitest'
import { ShellBridge } from '../ShellBridge'
import { BRIDGE_SOURCE } from '../messages'

const ORIGIN = 'https://cms.test'

function makeEvent(data: unknown, origin = ORIGIN): MessageEvent {
  return { data, origin } as MessageEvent
}

describe('ShellBridge.receive', () => {
  it('dispatches a valid iframe->shell message', () => {
    const onMessage = vi.fn()
    const bridge = new ShellBridge({ contentWindow: null }, ORIGIN, onMessage)

    bridge.receive(makeEvent({ source: BRIDGE_SOURCE, type: 'pb:component-click', payload: { id: 'n1' } }))

    expect(onMessage).toHaveBeenCalledTimes(1)
    expect(onMessage.mock.calls[0][0].type).toBe('pb:component-click')
  })

  it('ignores a message from a different origin', () => {
    const onMessage = vi.fn()
    const bridge = new ShellBridge({ contentWindow: null }, ORIGIN, onMessage)

    bridge.receive(makeEvent({ source: BRIDGE_SOURCE, type: 'pb:ready' }, 'https://evil.test'))

    expect(onMessage).not.toHaveBeenCalled()
  })

  it('ignores unknown message types and foreign sources', () => {
    const onMessage = vi.fn()
    const bridge = new ShellBridge({ contentWindow: null }, ORIGIN, onMessage)

    bridge.receive(makeEvent({ source: BRIDGE_SOURCE, type: 'pb:nope' }))
    bridge.receive(makeEvent({ source: 'evil', type: 'pb:ready' }))
    bridge.receive(makeEvent('not-an-object'))

    expect(onMessage).not.toHaveBeenCalled()
  })
})

describe('ShellBridge.receive — source validation (hotfix)', () => {
  it('accepts a message whose event.source is the iframe content window', () => {
    const win = {} as Window
    const onMessage = vi.fn()
    const bridge = new ShellBridge({ contentWindow: win }, ORIGIN, onMessage)

    const event = { data: { source: BRIDGE_SOURCE, type: 'pb:ready' }, origin: ORIGIN, source: win } as unknown as MessageEvent
    bridge.receive(event)

    expect(onMessage).toHaveBeenCalledTimes(1)
  })

  it('ignores a same-origin message from a different window', () => {
    const win = {} as Window
    const other = {} as Window
    const onMessage = vi.fn()
    const bridge = new ShellBridge({ contentWindow: win }, ORIGIN, onMessage)

    const event = { data: { source: BRIDGE_SOURCE, type: 'pb:ready' }, origin: ORIGIN, source: other } as unknown as MessageEvent
    bridge.receive(event)

    expect(onMessage).not.toHaveBeenCalled()
  })

  it('stays tolerant when the content window is unknown (source check skipped)', () => {
    const onMessage = vi.fn()
    const bridge = new ShellBridge({ contentWindow: null }, ORIGIN, onMessage)

    const event = { data: { source: BRIDGE_SOURCE, type: 'pb:ready' }, origin: ORIGIN, source: {} as Window } as unknown as MessageEvent
    bridge.receive(event)

    expect(onMessage).toHaveBeenCalledTimes(1)
  })
})

describe('ShellBridge.send', () => {
  it('posts to the iframe content window with the target origin', () => {
    const postMessage = vi.fn()
    const bridge = new ShellBridge({ contentWindow: { postMessage } as unknown as Window }, ORIGIN, vi.fn())

    bridge.send('pb:update-payload', { html: '<x>' })

    expect(postMessage).toHaveBeenCalledWith(
      { source: BRIDGE_SOURCE, type: 'pb:update-payload', payload: { html: '<x>' } },
      ORIGIN,
    )
  })

  it('is a no-op when the iframe is not mounted', () => {
    const bridge = new ShellBridge({ contentWindow: null }, ORIGIN, vi.fn())
    expect(() => bridge.send('pb:refresh')).not.toThrow()
  })
})
