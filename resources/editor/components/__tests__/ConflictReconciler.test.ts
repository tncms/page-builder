import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, flushPromises } from '@vue/test-utils'
import ConflictReconciler from '../ConflictReconciler.vue'
import { useEditorStore } from '../../stores/editor'
import type { EditorContextData, LayoutContext, LayoutPayload } from '../../types'

// PB-PUBLISH-R1-H1 (Defect 2) — the conflict banner exposes an EXPLICIT, confirm-gated
// "Reload latest version" action. It is invisible without a real conflict, never replaces
// local work silently, keeps work on cancel, and delegates to store.reconcile() only after
// a second explicit confirmation.

function layout(overrides: Partial<LayoutContext> = {}): LayoutContext {
  return {
    editor_mode: 'builder',
    draft_payload: null,
    published_payload: null,
    has_draft: false,
    has_published: false,
    is_published_current: true,
    version_token: 'tok-newest',
    last_saved_at: null,
    published_at: null,
    ...overrides,
  }
}

function context(): EditorContextData {
  return {
    owner: { type: 'page', id: 1, label: 'Page', title: 'T', edit_url: '' },
    layout: layout({
      has_draft: true,
      draft_payload: { schema: 'x', sections: [{ id: 'server', type: 'hero', data: {}, blocks: [] }] },
    }),
    metadata: { version: '1', sections: [{ type: 'hero' }], blocks: [{ type: 'heading' }], tokens: {} } as never,
    permissions: { can_edit: true, can_publish: true },
  }
}

function conflicted(store: ReturnType<typeof useEditorStore>, getContext = vi.fn(async () => context())) {
  store.metadata = { version: '1', sections: [{ type: 'hero' }], blocks: [{ type: 'heading' }], tokens: {} } as never
  store.currentPayload = {
    schema: 'x',
    sections: [{ id: 'local', type: 'hero', data: { title: 'LOCAL' }, blocks: [] }],
  } as unknown as LayoutPayload
  store.dirty = true
  store.conflict = true
  store.versionToken = 'tok-stale'
  store.error = 'This layout changed in another session.'
  ;(store as unknown as { client: unknown }).client = { getContext }
  return getContext
}

describe('ConflictReconciler', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('renders nothing without a real conflict', () => {
    const store = useEditorStore()
    store.conflict = false
    const wrapper = mount(ConflictReconciler)
    expect(wrapper.find('[data-pb-conflict]').exists()).toBe(false)
    expect(wrapper.find('[data-pb-conflict-reload]').exists()).toBe(false)
  })

  it('shows the conflict banner and a reload action when a conflict is flagged', () => {
    const store = useEditorStore()
    conflicted(store)
    const wrapper = mount(ConflictReconciler)
    expect(wrapper.find('[data-pb-conflict]').exists()).toBe(true)
    expect(wrapper.find('[data-pb-conflict-reload]').exists()).toBe(true)
    // No confirmation prompt is shown until the user arms it.
    expect(wrapper.find('[data-pb-conflict-confirm]').exists()).toBe(false)
  })

  it('the reload action never replaces local work on the first click — it only arms a confirmation', async () => {
    const store = useEditorStore()
    const getContext = conflicted(store)
    const wrapper = mount(ConflictReconciler)

    await wrapper.find('[data-pb-conflict-reload]').trigger('click')

    // No fetch, no state change yet — just the confirmation surface.
    expect(getContext).not.toHaveBeenCalled()
    expect(store.currentPayload.sections[0].id).toBe('local')
    expect(store.conflict).toBe(true)
    expect(wrapper.find('[data-pb-conflict-warning]').exists()).toBe(true)
    expect(wrapper.find('[data-pb-conflict-confirm]').exists()).toBe(true)
    expect(wrapper.find('[data-pb-conflict-cancel]').exists()).toBe(true)
  })

  it('cancel keeps the local work and dismisses the confirmation', async () => {
    const store = useEditorStore()
    const getContext = conflicted(store)
    const wrapper = mount(ConflictReconciler)

    await wrapper.find('[data-pb-conflict-reload]').trigger('click')
    await wrapper.find('[data-pb-conflict-cancel]').trigger('click')

    expect(getContext).not.toHaveBeenCalled()
    expect(store.currentPayload.sections[0].id).toBe('local')
    expect(store.conflict).toBe(true)
    // Back to the initial armed-off state.
    expect(wrapper.find('[data-pb-conflict-confirm]').exists()).toBe(false)
    expect(wrapper.find('[data-pb-conflict-reload]').exists()).toBe(true)
  })

  it('confirming fetches the latest context, adopts server state + token, and clears the conflict', async () => {
    const store = useEditorStore()
    const getContext = conflicted(store)
    const wrapper = mount(ConflictReconciler)

    await wrapper.find('[data-pb-conflict-reload]').trigger('click')
    await wrapper.find('[data-pb-conflict-confirm]').trigger('click')
    await flushPromises()

    expect(getContext).toHaveBeenCalledTimes(1)
    expect(store.versionToken).toBe('tok-newest')
    expect(store.currentPayload.sections[0].id).toBe('server')
    expect(store.conflict).toBe(false)
    // Banner unmounts once the conflict clears.
    expect(wrapper.find('[data-pb-conflict]').exists()).toBe(false)
  })

  it('a failed reload preserves local work, keeps the conflict, and shows a truthful error', async () => {
    const store = useEditorStore()
    conflicted(
      store,
      vi.fn(async () => {
        throw new Error('Network down')
      }),
    )
    const wrapper = mount(ConflictReconciler)

    await wrapper.find('[data-pb-conflict-reload]').trigger('click')
    await wrapper.find('[data-pb-conflict-confirm]').trigger('click')
    await flushPromises()

    expect(store.currentPayload.sections[0].id).toBe('local')
    expect(store.conflict).toBe(true)
    expect(store.versionToken).toBe('tok-stale')
    expect(store.error).toBe('Network down')
    // Banner still present with the reload action available for another attempt.
    expect(wrapper.find('[data-pb-conflict]').exists()).toBe(true)
    expect(wrapper.find('[data-pb-conflict-reload]').exists()).toBe(true)
  })
})
