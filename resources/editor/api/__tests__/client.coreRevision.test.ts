import { describe, it, expect, vi, afterEach } from 'vitest'
import { ApiClient, LayoutConflictError } from '../client'
import type { BootData } from '../../types'

// PB-CORE37-R1 — the editor echoes the Core content revision it was opened with on
// publish and translation creation, adopts ONLY a committed value from a successful
// response, and never advances it on a conflict (explicit reload stays required).

function boot(revision: number | null): BootData {
  return {
    csrf: 'tok',
    api: { publish: '/publish', context: '/context' },
    context: { owner: { type: 'page', id: 7, label: 'Page', title: 'Home', edit_url: null, content_revision: revision } },
  } as unknown as BootData
}

type Reply = { status: number; body: unknown }

function sequence(...replies: Reply[]) {
  const queue = [...replies]
  return vi.fn(async () => {
    const reply = queue.shift() as Reply
    return { ok: reply.status < 400, status: reply.status, json: async () => reply.body }
  }) as unknown as typeof fetch
}

function bodyOf(fetchMock: typeof fetch, call: number): Record<string, unknown> {
  return JSON.parse((fetchMock as unknown as ReturnType<typeof vi.fn>).mock.calls[call][1].body)
}

afterEach(() => vi.restoreAllMocks())

describe('ApiClient Core revision (PB-CORE37-R1)', () => {
  it('publishes with the boot revision and adopts the committed one', async () => {
    const fetchMock = sequence(
      { status: 200, body: { success: true, data: { version_token: 't2', content_revision: 4 } } },
      { status: 200, body: { success: true, data: { version_token: 't3', content_revision: 4 } } },
    )
    vi.stubGlobal('fetch', fetchMock)
    const client = new ApiClient(boot(3))

    await client.publish('t1')
    await client.publish('t2')

    expect(bodyOf(fetchMock, 0)).toEqual({ token: 't1', content_revision: 3 })
    expect(bodyOf(fetchMock, 1)).toEqual({ token: 't2', content_revision: 4 })
    expect(client.coreRevision).toBe(4)
  })

  it('creates a translation with the revision and adopts the committed one', async () => {
    const fetchMock = sequence({
      status: 201,
      body: { success: true, data: { locale: 'vi', created: true, content_translation_id: 9, editor_url: '/e', content_revision: 6 } },
    })
    vi.stubGlobal('fetch', fetchMock)
    const client = new ApiClient(boot(5))

    const created = await client.createTranslation('/translations', 'vi')

    expect(bodyOf(fetchMock, 0)).toEqual({ locale: 'vi', content_revision: 5 })
    expect(created.editor_url).toBe('/e')
    expect(client.coreRevision).toBe(6)
  })

  it('keeps the old revision on a Core revision conflict (typed 409)', async () => {
    const fetchMock = sequence({
      status: 409,
      body: { success: false, error: 'pb_core_revision_conflict', message: 'changed', data: { content_revision: 9 } },
    })
    vi.stubGlobal('fetch', fetchMock)
    const client = new ApiClient(boot(5))

    await expect(client.publish('t1')).rejects.toBeInstanceOf(LayoutConflictError)
    expect(client.coreRevision).toBe(5)
  })

  it('sends null when the boot context carries no revision', async () => {
    const fetchMock = sequence({ status: 200, body: { success: true, data: { version_token: 't2' } } })
    vi.stubGlobal('fetch', fetchMock)

    await new ApiClient(boot(null)).publish('t1')

    expect(bodyOf(fetchMock, 0)).toEqual({ token: 't1', content_revision: null })
  })
})
