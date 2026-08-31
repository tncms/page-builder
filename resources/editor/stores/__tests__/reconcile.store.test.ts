import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import { LayoutConflictError, LAYOUT_VERSION_CONFLICT } from '../../api/client'
import type { EditorContextData, EditorMetadata, LayoutContext, LayoutPayload } from '../../types'

// PB-PUBLISH-R1-H1 (Defect 2) — explicit conflict reconciliation at the store authority.
// reconcile() is the only supported recovery from a typed version conflict: it fetches the
// newest authoritative context and atomically replaces state + token, clearing the conflict
// ONLY on success. It never runs automatically, never auto-merges, and preserves local work
// on failure.

function layout(overrides: Partial<LayoutContext> = {}): LayoutContext {
  return {
    editor_mode: 'builder',
    draft_payload: null,
    published_payload: null,
    has_draft: false,
    has_published: false,
    is_published_current: true,
    version_token: 'tok-0',
    last_saved_at: null,
    published_at: null,
    ...overrides,
  }
}

function context(overrides: Partial<LayoutContext> = {}): EditorContextData {
  return {
    owner: { type: 'page', id: 1, label: 'Page', title: 'T', edit_url: '' },
    layout: layout(overrides),
    metadata: { version: '1', sections: [{ type: 'hero' }], blocks: [{ type: 'heading' }], tokens: {} } as never,
    permissions: { can_edit: true, can_publish: true },
  }
}

function staleWork(store: ReturnType<typeof useEditorStore>) {
  store.metadata = { version: '1', sections: [{ type: 'hero' }], blocks: [{ type: 'heading' }], tokens: {} } as never
  store.currentPayload = {
    schema: 'x',
    sections: [{ id: 'local', type: 'hero', data: { title: 'LOCAL' }, blocks: [] }],
  } as unknown as LayoutPayload
  store.dirty = true
  store.conflict = true
  store.saveFailed = true
  store.versionToken = 'tok-stale'
  store.error = 'This layout changed in another session.'
}

function conflict() {
  return new LayoutConflictError('This layout changed in another session.', LAYOUT_VERSION_CONFLICT, { token: 'newer' })
}

describe('editor store — conflict reconciliation (Defect 2)', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('a conflict never runs reconcile automatically — local work stays until asked', async () => {
    const store = useEditorStore()
    staleWork(store)
    const getContext = vi.fn(async () => context())
    ;(store as unknown as { client: unknown }).client = { getContext }

    // No reconcile call happened just because a conflict exists.
    expect(getContext).not.toHaveBeenCalled()
    expect(store.conflict).toBe(true)
    expect(store.currentPayload.sections[0].id).toBe('local')
  })

  it('confirmed reconcile fetches latest context and atomically adopts the new state + token', async () => {
    const store = useEditorStore()
    staleWork(store)
    const getContext = vi.fn(async () =>
      context({
        version_token: 'tok-newest',
        has_draft: true,
        has_published: true,
        draft_payload: { schema: 'x', sections: [{ id: 'server', type: 'hero', data: { title: 'SERVER' }, blocks: [] }] },
      }),
    )
    ;(store as unknown as { client: unknown }).client = { getContext }

    const ok = await store.reconcile()

    expect(ok).toBe(true)
    expect(getContext).toHaveBeenCalledTimes(1)
    // State + token replaced atomically; conflict cleared; editor now clean on server truth.
    expect(store.versionToken).toBe('tok-newest')
    expect(store.currentPayload.sections[0].id).toBe('server')
    expect(store.conflict).toBe(false)
    expect(store.dirty).toBe(false)
  })

  it('a failed reconcile preserves the user\'s current local work and shows a truthful error', async () => {
    const store = useEditorStore()
    staleWork(store)
    const getContext = vi.fn(async () => {
      throw new Error('Network down')
    })
    ;(store as unknown as { client: unknown }).client = { getContext }

    const ok = await store.reconcile()

    expect(ok).toBe(false)
    // Stale local work, the conflict, and a truthful error are all preserved.
    expect(store.currentPayload.sections[0].id).toBe('local')
    expect(store.conflict).toBe(true)
    expect(store.versionToken).toBe('tok-stale')
    expect(store.error).toBe('Network down')
    expect(store.reconciling).toBe(false)
  })

  it('after a successful reconcile, a fresh edit + Save + Publish work again', async () => {
    const store = useEditorStore()
    staleWork(store)
    const client = {
      getContext: vi.fn(async () => context({ version_token: 'tok-newest', has_draft: true })),
      saveDraft: vi.fn(async () => layout({ has_draft: true, version_token: 'tok-saved' })),
      publish: vi.fn(async () => layout({ has_draft: true, has_published: true, version_token: 'tok-published' })),
    }
    ;(store as unknown as { client: unknown }).client = client

    expect(await store.reconcile()).toBe(true)
    expect(store.versionToken).toBe('tok-newest')

    // A new intentional edit + save + publish now succeed from reconciled truth.
    store.currentPayload = {
      schema: 'x',
      sections: [{ id: 'edit', type: 'hero', data: {}, blocks: [{ id: 'b1', type: 'heading', data: {} }] }],
    } as unknown as LayoutPayload
    store.markDirty()

    expect(await store.saveDraft()).toBe(true)
    expect(client.saveDraft).toHaveBeenCalledWith(store.currentPayload, 'tok-newest')
    expect(store.versionToken).toBe('tok-saved')

    expect(await store.publish()).toBe(true)
    expect(store.versionToken).toBe('tok-published')
    expect(store.conflict).toBe(false)
  })

  it('reconcile is re-entrancy safe — a second call while one is in flight is a no-op', async () => {
    const store = useEditorStore()
    staleWork(store)
    let resolve!: (v: EditorContextData) => void
    const getContext = vi.fn(() => new Promise<EditorContextData>((r) => (resolve = r)))
    ;(store as unknown as { client: unknown }).client = { getContext }

    const first = store.reconcile()
    const second = await store.reconcile() // returns immediately (reconciling guard)
    expect(second).toBe(false)
    expect(getContext).toHaveBeenCalledTimes(1)

    resolve(context({ version_token: 'tok-newest' }))
    expect(await first).toBe(true)
    expect(store.conflict).toBe(false)
  })
})
