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
    iframeUrl: 'http://cms.test/page-builder/page/7/editor-document',
    frontendOrigin: 'http://stale.example',
    publicUrl: 'http://cms.test/page',
    publicUrlAvailable: true,
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

  it('exposes the private editor-document URL as the iframe document (PB-DRAFT-PREVIEW-R1)', () => {
    const shell = useShellStore()
    shell.bootstrap(boot())
    // Identity-based private route — never the public page with ?pb_editor=1.
    expect(shell.iframeUrl).toContain('/page-builder/page/7/editor-document')
    expect(shell.iframeUrl).not.toContain('pb_editor=1')
    // The public URL stays a DISTINCT field for "View Page".
    expect(shell.publicUrl).toBe('http://cms.test/page')
    expect(shell.iframeUrl).not.toBe(shell.publicUrl)
  })

  it('falls back to the server origin when no iframe URL is present', () => {
    const shell = useShellStore()
    shell.bootstrap(boot({ iframeUrl: '', frontendOrigin: 'http://fallback.test' }))
    expect(shell.frontendOrigin).toBe('http://fallback.test')
  })
})

describe('publicUrlAvailable (PB-DRAFT-PREVIEW-R1)', () => {
  it('defaults to true when the boot omits it (published owner / older payload)', () => {
    const shell = useShellStore()
    shell.bootstrap(boot({ publicUrlAvailable: undefined }))
    expect(shell.publicUrlAvailable).toBe(true)
  })

  it('is false when the content entity is unpublished', () => {
    const shell = useShellStore()
    shell.bootstrap(boot({ publicUrlAvailable: false }))
    expect(shell.publicUrlAvailable).toBe(false)
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
