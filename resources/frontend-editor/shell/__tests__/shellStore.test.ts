import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useShellStore } from '../shellStore'
import type { FrontendBootData } from '../types'

function boot(overrides: Partial<FrontendBootData> = {}): FrontendBootData {
  return {
    mount: '#pb-frontend-editor-root',
    csrf: 'x',
    context: null,
    exitUrl: null,
    iframeUrl: 'http://cms.test/page?pb_editor=1&pb_owner_type=page&pb_owner_id=7',
    frontendOrigin: 'http://stale.example',
    publicUrl: 'http://cms.test/page',
    structureEditUrl: '/admin/page-builder/page/7/edit',
    api: { context: '', draft: '', publish: '', command: '', preview: '' },
    ...overrides,
  } as FrontendBootData
}

beforeEach(() => setActivePinia(createPinia()))

describe('frontendOrigin derivation (hotfix)', () => {
  it('derives the origin from the actual iframe URL, not the server-provided value', () => {
    const shell = useShellStore()
    shell.bootstrap(boot())
    // The blade-provided frontendOrigin is deliberately "stale.example"; the URL
    // origin must win so postMessage targeting/validation matches the real iframe.
    expect(shell.frontendOrigin).toBe('http://cms.test')
  })

  it('preserves the pb_editor query on the iframe URL', () => {
    const shell = useShellStore()
    shell.bootstrap(boot())
    expect(shell.iframeUrl).toContain('pb_editor=1')
    expect(shell.iframeUrl).toContain('pb_owner_type=page')
    expect(shell.iframeUrl).toContain('pb_owner_id=7')
  })

  it('falls back to the server origin when no iframe URL is present', () => {
    const shell = useShellStore()
    shell.bootstrap(boot({ iframeUrl: '', frontendOrigin: 'http://fallback.test' }))
    expect(shell.frontendOrigin).toBe('http://fallback.test')
  })
})

describe('iframe readiness state (hotfix)', () => {
  it('clears the timed-out flag when readiness arrives', () => {
    const shell = useShellStore()
    shell.setIframeTimedOut(true)
    expect(shell.iframeTimedOut).toBe(true)
    shell.setIframeReady(true)
    expect(shell.iframeReady).toBe(true)
    expect(shell.iframeTimedOut).toBe(false)
  })

  it('resets both flags before a (re)load', () => {
    const shell = useShellStore()
    shell.setIframeReady(true)
    shell.setIframeTimedOut(true)
    shell.resetIframeState()
    expect(shell.iframeReady).toBe(false)
    expect(shell.iframeTimedOut).toBe(false)
  })

  it('carries a precise reason with the timeout and clears it when cleared', () => {
    const shell = useShellStore()
    shell.setIframeTimedOut(true, 'messages were rejected')
    expect(shell.iframeTimedOut).toBe(true)
    expect(shell.timeoutReason).toBe('messages were rejected')

    shell.setIframeTimedOut(false)
    expect(shell.timeoutReason).toBeNull()
  })

  it('tracks a protocol mismatch as its own terminal state', () => {
    const shell = useShellStore()
    shell.setProtocolMismatch(true)
    expect(shell.iframeProtocolMismatch).toBe(true)

    // Readiness clears every failure flag (fresh handshake succeeded).
    shell.setIframeReady(true)
    expect(shell.iframeProtocolMismatch).toBe(false)
  })

  it('resets the mismatch + reason before a (re)load', () => {
    const shell = useShellStore()
    shell.setProtocolMismatch(true)
    shell.setIframeTimedOut(true, 'boom')
    shell.resetIframeState()
    expect(shell.iframeProtocolMismatch).toBe(false)
    expect(shell.timeoutReason).toBeNull()
  })
})
