import { describe, it, expect } from 'vitest'
import {
  BRIDGE_SOURCE,
  IFRAME_TO_SHELL,
  PB_BRIDGE_PROTOCOL_VERSION,
  SHELL_TO_IFRAME,
  isAllowedOrigin,
  parseBridgeMessage,
} from '../messages'

describe('PB_BRIDGE_PROTOCOL_VERSION', () => {
  it('is a stable numeric version rides in the readiness handshake', () => {
    // Shipped in both bundles; a mismatch between them signals a stale cache.
    expect(typeof PB_BRIDGE_PROTOCOL_VERSION).toBe('number')
    expect(Number.isInteger(PB_BRIDGE_PROTOCOL_VERSION)).toBe(true)
  })
})

describe('parseBridgeMessage', () => {
  it('accepts a well-formed message whose type is in the allow-list', () => {
    const msg = parseBridgeMessage(
      { source: BRIDGE_SOURCE, type: 'pb:component-click', payload: { id: 'x' } },
      IFRAME_TO_SHELL,
    )
    expect(msg).not.toBeNull()
    expect(msg?.type).toBe('pb:component-click')
    expect(msg?.payload).toEqual({ id: 'x' })
  })

  it('rejects an unknown/unlisted type', () => {
    expect(
      parseBridgeMessage({ source: BRIDGE_SOURCE, type: 'pb:evil' }, IFRAME_TO_SHELL),
    ).toBeNull()
  })

  it('rejects a type meant for the other direction', () => {
    // pb:update-payload is shell->iframe; not valid on the iframe->shell channel.
    expect(
      parseBridgeMessage({ source: BRIDGE_SOURCE, type: 'pb:update-payload' }, IFRAME_TO_SHELL),
    ).toBeNull()
    expect(
      parseBridgeMessage({ source: BRIDGE_SOURCE, type: 'pb:update-payload' }, SHELL_TO_IFRAME),
    ).not.toBeNull()
  })

  it('rejects a foreign source', () => {
    expect(
      parseBridgeMessage({ source: 'other', type: 'pb:ready' }, IFRAME_TO_SHELL),
    ).toBeNull()
  })

  it('rejects non-object / null / string payloads', () => {
    expect(parseBridgeMessage(null, IFRAME_TO_SHELL)).toBeNull()
    expect(parseBridgeMessage('pb:ready', IFRAME_TO_SHELL)).toBeNull()
    expect(parseBridgeMessage(42, IFRAME_TO_SHELL)).toBeNull()
  })
})

describe('isAllowedOrigin', () => {
  it('matches exact origin', () => {
    expect(isAllowedOrigin('https://a.test', 'https://a.test')).toBe(true)
    expect(isAllowedOrigin('https://evil.test', 'https://a.test')).toBe(false)
  })

  it('treats * as a wildcard', () => {
    expect(isAllowedOrigin('https://anything.test', '*')).toBe(true)
  })
})

describe('readiness handshake vocabulary (hotfix)', () => {
  it('allows pb:handshake shell → iframe', () => {
    expect(SHELL_TO_IFRAME).toContain('pb:handshake')
    const msg = parseBridgeMessage({ source: BRIDGE_SOURCE, type: 'pb:handshake' }, SHELL_TO_IFRAME)
    expect(msg?.type).toBe('pb:handshake')
  })

  it('allows pb:rendered iframe → shell', () => {
    expect(IFRAME_TO_SHELL).toContain('pb:rendered')
    const msg = parseBridgeMessage({ source: BRIDGE_SOURCE, type: 'pb:rendered' }, IFRAME_TO_SHELL)
    expect(msg?.type).toBe('pb:rendered')
  })

  it('does not accept pb:handshake as an iframe → shell message', () => {
    expect(parseBridgeMessage({ source: BRIDGE_SOURCE, type: 'pb:handshake' }, IFRAME_TO_SHELL)).toBeNull()
  })
})
