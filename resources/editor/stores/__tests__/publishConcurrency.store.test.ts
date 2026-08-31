import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import { LayoutConflictError, LAYOUT_VERSION_CONFLICT } from '../../api/client'
import type { EditorMetadata, LayoutContext, LayoutPayload } from '../../types'

// PB-PUBLISH-R1 — client side of INV-PUB-06 (Version Safety). The store echoes the
// server-issued version token on every save/publish, adopts a new token ONLY from a
// successful response, and on a typed conflict keeps local work, refuses a false
// success, and stops the publish chain when Save Draft conflicts.

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

function conflict() {
  return new LayoutConflictError('This layout changed in another session.', LAYOUT_VERSION_CONFLICT, {
    token: 'tok-server-newer',
    has_published: true,
  })
}

function scene() {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = {
    schema: 'x',
    sections: [{ id: 'sec', type: 'hero', data: {}, blocks: [{ id: 'b1', type: 'heading', data: {} }] }],
  } as unknown as LayoutPayload
  store.metadata = {
    version: '1',
    sections: [{ type: 'hero' }],
    blocks: [{ type: 'heading' }],
    tokens: {},
  } as unknown as EditorMetadata
  return store
}

describe('store save/publish — optimistic concurrency (INV-PUB-06)', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('echoes the current version token on Save Draft', async () => {
    const store = scene()
    store.versionToken = 'tok-7'
    const saveDraft = vi.fn(async () => layout({ has_draft: true, version_token: 'tok-8' }))
    ;(store as unknown as { client: unknown }).client = { saveDraft, publish: vi.fn() }

    await store.saveDraft()

    expect(saveDraft).toHaveBeenCalledWith(store.currentPayload, 'tok-7')
  })

  it('adopts the new token from a successful Save Draft, and again from Publish', async () => {
    const store = scene()
    store.versionToken = 'tok-0'
    ;(store as unknown as { client: unknown }).client = {
      saveDraft: vi.fn(async () => layout({ has_draft: true, version_token: 'tok-saved' })),
      publish: vi.fn(async () => layout({ has_draft: true, has_published: true, version_token: 'tok-published' })),
    }

    await store.saveDraft()
    expect(store.versionToken).toBe('tok-saved')

    await store.publish()
    expect(store.versionToken).toBe('tok-published')
    expect(store.conflict).toBe(false)
  })

  it('a stale Save Draft keeps local work, flags conflict, and never reads as saved', async () => {
    const store = scene()
    store.versionToken = 'tok-stale'
    store.dirty = true
    ;(store as unknown as { client: unknown }).client = {
      saveDraft: vi.fn(async () => {
        throw conflict()
      }),
      publish: vi.fn(),
    }

    const ok = await store.saveDraft()

    expect(ok).toBe(false)
    expect(store.conflict).toBe(true)
    expect(store.saveFailed).toBe(true)
    expect(store.savedFlash).toBe(false)
    // Local work is preserved and the stale token is NOT replaced (explicit reload only).
    expect(store.dirty).toBe(true)
    expect(store.versionToken).toBe('tok-stale')
    expect(store.saveState).toBe('failed')
  })

  it('a Save Draft conflict stops the Publish chain — publish never runs and never reports success', async () => {
    const store = scene()
    store.versionToken = 'tok-stale'
    const publish = vi.fn(async () => layout({ has_published: true }))
    ;(store as unknown as { client: unknown }).client = {
      saveDraft: vi.fn(async () => {
        throw conflict()
      }),
      publish,
    }

    const ok = await store.publish()

    expect(ok).toBe(false)
    expect(publish).not.toHaveBeenCalled()
    expect(store.conflict).toBe(true)
    expect(store.publicationState).not.toBe('published')
  })

  it('a conflict at Publish (save ok, publish stale) flags conflict and refuses a false success', async () => {
    const store = scene()
    store.versionToken = 'tok-0'
    ;(store as unknown as { client: unknown }).client = {
      saveDraft: vi.fn(async () => layout({ has_draft: true, version_token: 'tok-saved' })),
      publish: vi.fn(async () => {
        throw conflict()
      }),
    }

    const ok = await store.publish()

    expect(ok).toBe(false)
    expect(store.conflict).toBe(true)
    expect(store.publishFailed).toBe(true)
    expect(store.publicationState).toBe('publish_failed')
  })

  it('an explicit reload (applyContext) reconciles: adopts the newest token and clears the conflict', async () => {
    const store = scene()
    store.versionToken = 'tok-stale'
    store.conflict = true

    store.applyContext({
      owner: { type: 'page', id: 1, label: 'Page', title: 'T', edit_url: '' },
      layout: layout({ has_draft: true, has_published: true, version_token: 'tok-newest' }),
      metadata: store.metadata as EditorMetadata,
      permissions: { can_edit: true, can_publish: true },
    } as never)

    expect(store.conflict).toBe(false)
    expect(store.versionToken).toBe('tok-newest')
  })
})
