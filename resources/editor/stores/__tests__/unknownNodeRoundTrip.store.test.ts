import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import { LayoutConflictError, LAYOUT_VERSION_CONFLICT } from '../../api/client'
import type { EditorContextData, LayoutPayload } from '../../types'

// PB-TEMPLATE-REGISTRY-1-H3 (Gap A) — an unknown node (a section/block type the editor has
// no definition for, carrying arbitrary JSON data) must survive the REAL editor store's
// load -> unrelated edit -> Save Draft / Publish serialization path byte-for-byte. This is
// the durable regression lock the H1/H2 source inspection could not provide: it drives the
// store's public commands + the mocked API client seam and deep-compares the outgoing payload,
// so a future known-type filter, schema sanitizer, or serializer that drops unknown nodes fails
// this test meaningfully.

// A section type + a nested block type the editor has no metadata for, with arbitrary nested
// JSON (strings, booleans, numbers, null, nested arrays/objects) and a stable id.
function unknownSection(): Record<string, unknown> {
  return {
    id: 'unknown-1',
    type: 'theme-mystery-widget',
    data: {
      headline: 'keep me',
      count: 42,
      enabled: true,
      empty: null,
      nested: { list: [1, 'two', false, null, { deep: 'x' }], flag: false },
    },
    blocks: [
      { id: 'unknown-block-1', type: 'exotic-block', data: { note: 'nested unknown', arr: [true, 2, 'three'] }, blocks: [] },
    ],
  }
}

function knownSection(): Record<string, unknown> {
  return { id: 'known-1', type: 'hero', data: { title: 'Original' }, blocks: [] }
}

// A permissive (empty-registry) metadata — the "usable before metadata loads" mode — so the
// pre-save layout validator never blocks on an unregistered type. This isolates the concern to
// serialization fidelity, not the separate save-block gate.
function permissiveMetadata() {
  return { version: '1', sections: [], blocks: [], tokens: {} }
}

function context(): EditorContextData {
  return {
    owner: { type: 'page', id: 1 },
    layout: {
      version_token: 'tok-1',
      is_published_current: false,
      has_draft: true,
      has_published: false,
      draft_payload: { schema: 'v1', sections: [unknownSection(), knownSection()] },
      published_payload: null,
    },
    metadata: permissiveMetadata(),
    permissions: { can_publish: true },
  } as unknown as EditorContextData
}

// A faithful stand-in for the server command engine: it applies ONLY the requested data edit to
// the named node and returns the whole payload otherwise verbatim (exactly what a correct server
// does), so any loss of the unknown node can only come from the store/serializer under test.
function fakeRunCommand() {
  return vi.fn(async (payload: LayoutPayload, command: string, params: Record<string, unknown>) => {
    const next = JSON.parse(JSON.stringify(payload)) as { sections: Array<Record<string, unknown>> }
    if (command === 'update-component-data') {
      const target = next.sections.find((s) => s.id === params.component_id)
      if (target) target.data = { ...(target.data as object), ...(params.data as object) }
    }
    return { payload: next, selection: { selected: (params.component_id as string) ?? null } }
  })
}

function scene() {
  setActivePinia(createPinia())
  const store = useEditorStore()
  const client = {
    getContext: vi.fn(),
    runCommand: fakeRunCommand(),
    saveDraft: vi.fn(async (_payload: LayoutPayload, _token: string | null) => ({ version_token: 'tok-2', is_published_current: false, has_draft: true, has_published: false }) as never),
    publish: vi.fn(async (_token: string | null) => ({ version_token: 'tok-3', is_published_current: true, has_draft: true, has_published: true }) as never),
  }
  ;(store as unknown as { client: unknown }).client = client
  store.applyContext(context())
  return { store, client }
}

// Deep-read the unknown section from a captured payload argument.
function sentUnknown(payload: unknown): Record<string, unknown> | undefined {
  const sections = (payload as { sections?: Array<Record<string, unknown>> }).sections
  return sections?.find((s) => s.id === 'unknown-1')
}

beforeEach(() => {
  vi.useFakeTimers()
})
afterEach(() => {
  useEditorStore().teardownUnloadGuard?.()
  vi.useRealTimers()
})

describe('unknown-node lossless round trip through Save Draft', () => {
  it('retains the unknown node (and its nested unknown block) after applyContext', () => {
    const { store } = scene()
    const loaded = store.currentPayload.sections.find((s) => (s as unknown as Record<string, unknown>).id === 'unknown-1')
    expect(loaded).toBeDefined()
    expect(loaded).toEqual(unknownSection())
  })

  it('an unrelated edit to a known sibling leaves the unknown node byte-for-byte identical', async () => {
    const { store } = scene()
    const pristine = unknownSection()

    const changed = await store.updateComponentDataById('known-1', { title: 'Edited' })
    expect(changed).toBe(true)

    const known = store.currentPayload.sections.find((s) => (s as unknown as Record<string, unknown>).id === 'known-1') as unknown as Record<string, unknown>
    expect((known.data as Record<string, unknown>).title).toBe('Edited')
    // The unknown node is untouched.
    const unknown = store.currentPayload.sections.find((s) => (s as unknown as Record<string, unknown>).id === 'unknown-1')
    expect(unknown).toEqual(pristine)
  })

  it('sends the unknown node deeply intact to the draft endpoint, and keeps it after the response', async () => {
    const { store, client } = scene()
    const pristine = unknownSection()

    await store.updateComponentDataById('known-1', { title: 'Edited' })
    expect(await store.saveDraft()).toBe(true)

    expect(client.saveDraft).toHaveBeenCalledTimes(1)
    const sentPayload = client.saveDraft.mock.calls[0][0]
    const sent = sentUnknown(sentPayload)
    // type, id, data, nesting, order and child blocks are all preserved.
    expect(sent).toEqual(pristine)
    // Order preserved: unknown first, known second.
    expect((sentPayload as LayoutPayload).sections.map((s) => (s as unknown as Record<string, unknown>).id)).toEqual(['unknown-1', 'known-1'])

    // Applying the successful save response never mutates the local payload's unknown node.
    const afterSave = store.currentPayload.sections.find((s) => (s as unknown as Record<string, unknown>).id === 'unknown-1')
    expect(afterSave).toEqual(pristine)
  })

  it('the publish pre-save chain is equally lossless', async () => {
    const { store, client } = scene()
    const pristine = unknownSection()

    await store.updateComponentDataById('known-1', { title: 'Edited' })
    expect(await store.publish()).toBe(true)

    // Publish persists the current revision first, then publishes it.
    expect(client.saveDraft).toHaveBeenCalled()
    expect(client.publish).toHaveBeenCalledTimes(1)
    const sent = sentUnknown(client.saveDraft.mock.calls.at(-1)![0])
    expect(sent).toEqual(pristine)
  })
})

describe('failed save / conflict preserves the unknown node', () => {
  it('a version conflict keeps the unknown node intact, reports no false success, and does not reconcile', async () => {
    const { store, client } = scene()
    const pristine = unknownSection()
    client.saveDraft.mockRejectedValueOnce(new LayoutConflictError('changed elsewhere', LAYOUT_VERSION_CONFLICT, null))

    await store.updateComponentDataById('known-1', { title: 'Edited' })
    expect(await store.saveDraft()).toBe(false)

    expect(store.conflict).toBe(true)
    // No automatic reconciliation / context refetch, no deletion.
    expect(client.getContext).not.toHaveBeenCalled()
    const unknown = store.currentPayload.sections.find((s) => (s as unknown as Record<string, unknown>).id === 'unknown-1')
    expect(unknown).toEqual(pristine)
  })

  it('a generic save failure also preserves the unknown node with no false success', async () => {
    const { store, client } = scene()
    const pristine = unknownSection()
    client.saveDraft.mockRejectedValueOnce(new Error('network boom'))

    await store.updateComponentDataById('known-1', { title: 'Edited' })
    expect(await store.saveDraft()).toBe(false)

    expect(store.saveFailed).toBe(true)
    const unknown = store.currentPayload.sections.find((s) => (s as unknown as Record<string, unknown>).id === 'unknown-1')
    expect(unknown).toEqual(pristine)
  })
})
