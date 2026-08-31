import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import type { BootData, EditorContextData, LayoutContext, LayoutPayload } from '../../types'

// PB-PUBLISH-R1-H1 — Defect 1 contract test. Unlike the store unit test, this drives the
// REAL ApiClient over a mocked `fetch` that returns the EXACT server envelope
// ({ success, data: <summarize> }) and enforces the same optimistic CAS the server does.
// It proves the token flows end-to-end through the client's response normalization
// (request() -> json.data -> LayoutContext.version_token -> store) so a save that echoes
// a previous successful response's token can never spuriously 409 in a single editor.

// A miniature server: one owner, a content-hash-style token that advances on every write,
// and a compare-and-swap that 409s a stale/absent token exactly like EditorContextController.
function fakeServer() {
  let token = 'tok-0'
  let seq = 0
  const conflict = () =>
    new Response(
      JSON.stringify({
        success: false,
        error: 'page_builder.layout.version_conflict',
        message: 'This layout changed in another session.',
        data: { token },
      }),
      { status: 409, headers: { 'Content-Type': 'application/json' } },
    )
  const ok = (extra: Partial<LayoutContext>) =>
    new Response(
      JSON.stringify({
        success: true,
        data: {
          editor_mode: 'builder',
          draft_payload: { schema: 'x', sections: [] },
          published_payload: null,
          has_draft: true,
          has_published: false,
          is_published_current: false,
          version_token: token,
          last_saved_at: '2026-08-22T00:00:00+00:00',
          published_at: null,
          ...extra,
        },
      }),
      { status: 200, headers: { 'Content-Type': 'application/json' } },
    )

  const seen: Array<{ url: string; token: unknown }> = []

  const fetchImpl = vi.fn(async (url: string, init?: RequestInit) => {
    const body = init?.body ? (JSON.parse(String(init.body)) as { token?: unknown }) : {}
    seen.push({ url, token: body.token })
    const sent = typeof body.token === 'string' && body.token !== '' ? body.token : null

    if (url.endsWith('/draft') || url.endsWith('/publish')) {
      if (sent !== token) return conflict()
      // Advance the authoritative token on a successful write.
      token = `tok-${++seq}`
      return url.endsWith('/publish')
        ? ok({ has_published: true, is_published_current: true, version_token: token })
        : ok({ version_token: token })
    }
    return ok({})
  })

  return { fetchImpl, seen, current: () => token }
}

function bootContext(): EditorContextData {
  return {
    owner: { type: 'page', id: 1, label: 'Page', title: 'T', edit_url: '' },
    layout: {
      editor_mode: 'builder',
      draft_payload: { schema: 'x', sections: [] },
      published_payload: null,
      has_draft: false,
      has_published: false,
      is_published_current: true,
      version_token: 'tok-0',
      last_saved_at: null,
      published_at: null,
    },
    metadata: { version: '1', sections: [{ type: 'hero' }], blocks: [{ type: 'heading' }], tokens: {} } as never,
    permissions: { can_edit: true, can_publish: true },
  }
}

function boot(): BootData {
  return {
    mount: '#app',
    csrf: 'csrf-token',
    locale: 'en',
    context: bootContext(),
    exitUrl: null,
    api: {
      context: '/api/page-builder/editor/context/page/1',
      draft: '/api/page-builder/editor/context/page/1/draft',
      publish: '/api/page-builder/editor/context/page/1/publish',
      command: '/api/page-builder/state/command',
      preview: '/api/page-builder/editor/preview',
    },
  }
}

function seedPayload(store: ReturnType<typeof useEditorStore>) {
  store.currentPayload = {
    schema: 'x',
    sections: [{ id: 'sec', type: 'hero', data: {}, blocks: [{ id: 'b1', type: 'heading', data: {} }] }],
  } as unknown as LayoutPayload
  store.metadata = {
    version: '1',
    sections: [{ type: 'hero' }],
    blocks: [{ type: 'heading' }],
    tokens: {},
  } as never
}

describe('editor store — real-envelope token adoption contract (INV-PUB-06, Defect 1)', () => {
  let server: ReturnType<typeof fakeServer>

  beforeEach(() => {
    setActivePinia(createPinia())
    server = fakeServer()
    vi.stubGlobal('fetch', server.fetchImpl)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('adopts token T1 from context, then T2 from a successful Save, and sends T2 next', async () => {
    const store = useEditorStore()
    store.bootstrap(boot())
    seedPayload(store)
    expect(store.versionToken).toBe('tok-0')

    // Save #1 with the context token -> succeeds and adopts the advanced token.
    expect(await store.saveDraft()).toBe(true)
    const t1 = store.versionToken
    expect(t1).toBe('tok-1')

    // Save #2 must send T1 (not the stale tok-0) and succeed with no false conflict.
    expect(await store.saveDraft()).toBe(true)
    expect(store.conflict).toBe(false)
    expect(store.versionToken).toBe('tok-2')

    // The request the client sent for Save #2 carried the token Save #1 returned.
    const draftCalls = server.seen.filter((c) => c.url.endsWith('/draft'))
    expect(draftCalls.map((c) => c.token)).toEqual(['tok-0', 'tok-1'])
  })

  it('a successful Publish adopts its returned token and a repeat publish stays safe', async () => {
    const store = useEditorStore()
    store.bootstrap(boot())
    seedPayload(store)

    expect(await store.publish()).toBe(true)
    expect(store.conflict).toBe(false)
    // publish() first saves (tok-1) then publishes (tok-2).
    expect(store.versionToken).toBe('tok-2')

    // A second intentional edit + publish continues to work with the fresh token.
    seedPayload(store)
    store.markDirty()
    expect(await store.publish()).toBe(true)
    expect(store.conflict).toBe(false)
  })

  it('a genuinely stale token (concurrent writer) 409s without erasing the last good token or faking success', async () => {
    const store = useEditorStore()
    store.bootstrap(boot())
    seedPayload(store)

    // First save advances to tok-1 on both client and server.
    expect(await store.saveDraft()).toBe(true)
    expect(store.versionToken).toBe('tok-1')

    // Simulate a concurrent writer advancing the server past the client by forcing the
    // client to resend the now-consumed original token.
    store.versionToken = 'tok-0'
    store.markDirty()
    expect(await store.saveDraft()).toBe(false)
    expect(store.conflict).toBe(true)
    // Local work preserved; the stale token is NOT advanced or erased (explicit reload only).
    expect(store.versionToken).toBe('tok-0')
    expect(store.dirty).toBe(true)
  })

  it('a successful Save whose response carries an empty/absent token never ERASES the last good token', async () => {
    // Defensive boundary (NOT the Defect 1 root cause — that was a server-side reordered-key
    // token mismatch, fixed in PageBuilderLayout::tokenFor and covered by its PHP test): a 200
    // response that (for any transit/serialization reason) lacks a usable version_token must
    // not poison the store. Adopting '' / null / undefined would make the very next save send
    // an empty token and 409 with `token_required`.
    const store = useEditorStore()
    store.bootstrap(boot())
    seedPayload(store)

    // First save advances to tok-1 (good, adopted).
    expect(await store.saveDraft()).toBe(true)
    expect(store.versionToken).toBe('tok-1')

    // Now the server yields a malformed 200 with an EMPTY token for one save.
    const emptyOk = new Response(
      JSON.stringify({
        success: true,
        data: {
          editor_mode: 'builder',
          draft_payload: { schema: 'x', sections: [] },
          published_payload: null,
          has_draft: true,
          has_published: false,
          is_published_current: false,
          version_token: '',
          last_saved_at: null,
          published_at: null,
        },
      }),
      { status: 200, headers: { 'Content-Type': 'application/json' } },
    )
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => emptyOk.clone()),
    )
    store.markDirty()
    expect(await store.saveDraft()).toBe(true)

    // The known-good token survives — it is NOT overwritten by the empty response token.
    expect(store.versionToken).toBe('tok-1')
  })

  it('a successful Save whose response omits version_token entirely preserves the last good token', async () => {
    const store = useEditorStore()
    store.bootstrap(boot())
    seedPayload(store)
    expect(await store.saveDraft()).toBe(true)
    expect(store.versionToken).toBe('tok-1')

    const missingOk = new Response(
      JSON.stringify({
        success: true,
        data: {
          editor_mode: 'builder',
          draft_payload: { schema: 'x', sections: [] },
          published_payload: null,
          has_draft: true,
          has_published: false,
          is_published_current: false,
          last_saved_at: null,
          published_at: null,
        },
      }),
      { status: 200, headers: { 'Content-Type': 'application/json' } },
    )
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => missingOk.clone()),
    )
    store.markDirty()
    expect(await store.saveDraft()).toBe(true)
    expect(store.versionToken).toBe('tok-1')
  })
})
