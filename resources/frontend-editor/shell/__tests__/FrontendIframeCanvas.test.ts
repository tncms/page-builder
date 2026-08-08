import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'
import FrontendIframeCanvas from '../FrontendIframeCanvas.vue'
import { useShellStore } from '../shellStore'
import { ShellBridge } from '../../bridge/ShellBridge'
import { BRIDGE_SOURCE, PB_BRIDGE_PROTOCOL_VERSION } from '../../bridge/messages'
import type { FrontendBootData } from '../types'

const ORIGIN = 'http://localhost'
const IFRAME_URL = `${ORIGIN}/page?pb_editor=1&pb_owner_type=page&pb_owner_id=7`

function boot(): FrontendBootData {
  return {
    mount: '#pb-frontend-editor-root',
    csrf: 'x',
    context: null,
    exitUrl: null,
    iframeUrl: IFRAME_URL,
    frontendOrigin: ORIGIN,
    publicUrl: `${ORIGIN}/page`,
    structureEditUrl: '/admin/page-builder/page/7/edit',
    selected: 'pb-hero',
    nav: { structure: '/edit', live: '/frontend', public: `${ORIGIN}/page`, classic: null },
    api: { context: '', draft: '', publish: '', command: '', preview: '' },
  } as FrontendBootData
}

let wrapper: VueWrapper

function mountCanvas(): VueWrapper {
  wrapper = mount(FrontendIframeCanvas, { attachTo: document.body })
  return wrapper
}

function fireLoad(): void {
  wrapper.find('iframe').element.dispatchEvent(new Event('load'))
}

function dispatchReady(origin = ORIGIN, payload: Record<string, unknown> = {}): void {
  const event = new MessageEvent('message', {
    data: { source: BRIDGE_SOURCE, type: 'pb:ready', payload },
    origin,
  })
  window.dispatchEvent(event)
}

beforeEach(() => {
  setActivePinia(createPinia())
  useShellStore().bootstrap(boot())
})

afterEach(() => {
  wrapper?.unmount()
  vi.restoreAllMocks()
  vi.useRealTimers()
})

describe('Live Builder loading lifecycle (hotfix)', () => {
  it('shows the loading overlay until readiness arrives', () => {
    mountCanvas()
    expect(wrapper.find('.pb-fe-canvas__loading').text()).toContain('Loading preview…')
  })

  it('requests a handshake when the iframe loads', () => {
    const send = vi.spyOn(ShellBridge.prototype, 'send')
    mountCanvas()
    fireLoad()
    expect(send.mock.calls.map((c) => c[0])).toContain('pb:handshake')
  })

  it('clears the loading overlay when pb:ready arrives after load', async () => {
    mountCanvas()
    fireLoad()
    expect(wrapper.find('.pb-fe-canvas__loading').exists()).toBe(true)

    dispatchReady()
    await nextTick()

    expect(useShellStore().iframeReady).toBe(true)
    expect(wrapper.find('.pb-fe-canvas__loading').exists()).toBe(false)
  })

  it('ignores a pb:ready from a mismatched origin', async () => {
    mountCanvas()
    fireLoad()
    dispatchReady('http://evil.test')
    await nextTick()

    expect(useShellStore().iframeReady).toBe(false)
    expect(wrapper.find('.pb-fe-canvas__loading').exists()).toBe(true)
  })

  it('falls back to a retry state when readiness times out', async () => {
    vi.useFakeTimers()
    mountCanvas()
    fireLoad()

    vi.advanceTimersByTime(9000)
    await nextTick()

    const shell = useShellStore()
    expect(shell.iframeReady).toBe(false)
    expect(shell.iframeTimedOut).toBe(true)
    const error = wrapper.find('.pb-fe-canvas__loading--error')
    expect(error.exists()).toBe(true)
    expect(error.text()).toContain('did not initialize')
    expect(wrapper.find('.pb-fe-canvas__retry').exists()).toBe(true)
  })

  it('restarts the handshake on Retry', async () => {
    vi.useFakeTimers()
    const send = vi.spyOn(ShellBridge.prototype, 'send')
    mountCanvas()
    fireLoad()
    vi.advanceTimersByTime(9000)
    await nextTick()

    send.mockClear()
    await wrapper.find('.pb-fe-canvas__retry').trigger('click')

    const shell = useShellStore()
    expect(shell.iframeTimedOut).toBe(false)
    expect(wrapper.find('.pb-fe-canvas__loading--error').exists()).toBe(false)
    expect(wrapper.find('.pb-fe-canvas__loading').exists()).toBe(true) // spinner back
    expect(send.mock.calls.map((c) => c[0])).toContain('pb:handshake')
  })

  it('accepts a matching-protocol pb:ready and clears the overlay', async () => {
    mountCanvas()
    fireLoad()
    dispatchReady(ORIGIN, { protocol: PB_BRIDGE_PROTOCOL_VERSION })
    await nextTick()

    const shell = useShellStore()
    expect(shell.iframeReady).toBe(true)
    expect(shell.iframeProtocolMismatch).toBe(false)
    expect(wrapper.find('.pb-fe-canvas__loading').exists()).toBe(false)
  })

  it('shows an assets-out-of-date state on a protocol mismatch (stale cached bundle)', async () => {
    mountCanvas()
    fireLoad()
    // A stale iframe bundle answers the handshake but with an older protocol.
    dispatchReady(ORIGIN, { protocol: PB_BRIDGE_PROTOCOL_VERSION + 1 })
    await nextTick()

    const shell = useShellStore()
    expect(shell.iframeReady).toBe(false)
    expect(shell.iframeProtocolMismatch).toBe(true)

    const error = wrapper.find('.pb-fe-canvas__loading--error')
    expect(error.exists()).toBe(true)
    expect(error.text()).toContain('out of date')
    // Retry cannot fix a bundle mismatch — the recovery action is a hard refresh.
    expect(wrapper.find('.pb-fe-canvas__reload').exists()).toBe(true)
    expect(wrapper.find('.pb-fe-canvas__retry').exists()).toBe(false)
  })

  it('does not encode the selection into the iframe URL', () => {
    mountCanvas()
    const src = wrapper.find('iframe').attributes('src')
    expect(src).toBe(IFRAME_URL)
    expect(src).toContain('pb_editor=1')
    expect(src).not.toContain('selected=')
  })
})
