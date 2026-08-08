import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import type { EditorMetadata, LayoutContext, LayoutPayload } from '../../types'

// PB-STATE-2V — publication-state truth. Proves the status the editor renders is
// derived from server publish-currency (is_published_current) and a distinct
// publish-failed flag — never a reassuring "draft saved" that masks stale public
// content. A saved-but-unpublished draft reads 'unpublished'; a failed publish
// reads 'publish_failed'.

function layout(overrides: Partial<LayoutContext> = {}): LayoutContext {
  return {
    editor_mode: 'builder',
    draft_payload: null,
    published_payload: null,
    has_draft: false,
    has_published: false,
    is_published_current: true,
    last_saved_at: null,
    published_at: null,
    ...overrides,
  }
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

describe('store.publicationState — publish truth (PB-STATE-2V)', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('is "none" with no layout / nothing drafted', () => {
    const store = useEditorStore()
    expect(store.publicationState).toBe('none')
    store.layout = layout({ has_draft: false, has_published: false, is_published_current: true })
    expect(store.publicationState).toBe('none')
  })

  it('is "unsaved" while there are local unsaved edits', () => {
    const store = useEditorStore()
    store.layout = layout({ has_draft: true, has_published: true, is_published_current: true })
    store.dirty = true
    expect(store.publicationState).toBe('unsaved')
  })

  it('is "unpublished" when a saved draft is NOT reflected in published (public stale)', () => {
    const store = useEditorStore()
    store.layout = layout({ has_draft: true, has_published: true, is_published_current: false })
    expect(store.publicationState).toBe('unpublished')
    expect(store.isPublishedCurrent).toBe(false)
  })

  it('is "unpublished" for a saved draft that was never published', () => {
    const store = useEditorStore()
    store.layout = layout({ has_draft: true, has_published: false, is_published_current: false })
    expect(store.publicationState).toBe('unpublished')
  })

  it('is "published" only when the draft matches the published snapshot', () => {
    const store = useEditorStore()
    store.layout = layout({ has_draft: true, has_published: true, is_published_current: true })
    expect(store.publicationState).toBe('published')
    expect(store.isPublishedCurrent).toBe(true)
  })

  it('is "saving"/"publishing" while a request is in flight', () => {
    const store = useEditorStore()
    store.layout = layout({ has_draft: true, has_published: true, is_published_current: false })
    store.saving = true
    expect(store.publicationState).toBe('saving')
    store.saving = false
    store.publishing = true
    expect(store.publicationState).toBe('publishing')
  })

  it('a FAILED publish reads "publish_failed" — never "published" and never a safe fallback', async () => {
    const store = scene()
    store.dirty = true
    ;(store as unknown as { client: unknown }).client = {
      saveDraft: vi.fn(async () => layout({ has_draft: true, has_published: true, is_published_current: false })),
      publish: vi.fn(async () => {
        throw new Error('publish boom')
      }),
    }

    const ok = await store.publish()

    expect(ok).toBe(false)
    expect(store.publishFailed).toBe(true)
    expect(store.publicationState).toBe('publish_failed')
    // The stale published snapshot must never be reported as current.
    expect(store.publicationState).not.toBe('published')
  })

  it('a fresh save clears a prior publish failure (status is re-derived from the new draft)', async () => {
    const store = scene()
    ;(store as unknown as { client: unknown }).client = {
      saveDraft: vi.fn(async () => layout({ has_draft: true, has_published: true, is_published_current: false })),
      publish: vi.fn(async () => {
        throw new Error('publish boom')
      }),
    }
    await store.publish()
    expect(store.publishFailed).toBe(true)

    await store.saveDraft()

    expect(store.publishFailed).toBe(false)
    expect(store.publicationState).toBe('unpublished')
  })
})
