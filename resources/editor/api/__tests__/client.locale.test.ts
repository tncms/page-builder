import { describe, it, expect, vi, afterEach } from 'vitest'
import { ApiClient } from '../client'
import type { BootData } from '../../types'

// PB-STATE-2T — the editor propagates the editing locale to the render endpoints
// so previews resolve translatable envelopes under the same locale as the public
// page (the server validates + scopes it). Owner IDs are the only owner context;
// the effective owner is never computed client-side.

function boot(locale: string): BootData {
  return {
    csrf: 'tok',
    locale,
    api: { preview: '/api/page-builder/editor/preview' },
  } as unknown as BootData
}

function mockJson(data: unknown) {
  return vi.fn(async () => ({
    ok: true,
    json: async () => ({ success: true, data }),
  })) as unknown as typeof fetch
}

afterEach(() => vi.restoreAllMocks())

describe('ApiClient locale propagation (PB-STATE-2T)', () => {
  it('renderPreview posts the editing locale', async () => {
    const fetchMock = mockJson({ html: '<x/>' })
    vi.stubGlobal('fetch', fetchMock)

    await new ApiClient(boot('vi')).renderPreview({ schema: 's', sections: [] }, 'page', 1)

    const body = JSON.parse((fetchMock as unknown as ReturnType<typeof vi.fn>).mock.calls[0][1].body)
    expect(body.locale).toBe('vi')
    expect(body.owner_type).toBe('page')
  })

  it('renderComponentPreview posts the editing locale and targets /component-preview', async () => {
    const fetchMock = mockJson({ component_id: 'c1', html: '<x/>', kind: 'block', type: 'button', warnings: [] })
    vi.stubGlobal('fetch', fetchMock)

    await new ApiClient(boot('vi')).renderComponentPreview({ schema: 's', sections: [] }, 'c1', 'page', 1)

    const call = (fetchMock as unknown as ReturnType<typeof vi.fn>).mock.calls[0]
    expect(call[0]).toContain('/component-preview')
    const body = JSON.parse(call[1].body)
    expect(body.locale).toBe('vi')
    expect(body.component_id).toBe('c1')
  })
})
