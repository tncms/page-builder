import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import type { EditorContextData, LayoutPayload } from '../../types'

// PB-XREG-4-R1 — with a provider unavailable, the editor keeps saving unrelated edits while the
// unavailable subtree (node + descendants + parent + order) is offered no change at all. The
// server enforces the same rule; these tests lock the client so it never offers a change the
// server would refuse, and never blocks the unrelated work the remediation exists for.

function payload(): LayoutPayload {
  return {
    schema: 'pagebuilder/01',
    sections: [
      { id: 'h-top', type: 'hero', data: { title: 'Top' }, blocks: [] },
      {
        id: 'p-sec',
        type: 'travel-booking:tour-grid',
        data: { heading: 'Tours', future_key: { x: 1.5 } },
        blocks: [{ id: 'p-inner-head', type: 'heading', data: { text: 'Inside plugin' } }],
      },
      {
        id: 's-plain',
        type: 'section',
        data: {},
        blocks: [
          { id: 'b1', type: 'heading', data: { text: 'Before' } },
          { id: 't-badge', type: 'pbx4-aurora:badge', data: { text: 'New' } },
          { id: 'b2', type: 'heading', data: { text: 'After' } },
        ],
      },
    ],
  } as unknown as LayoutPayload
}

function metadata() {
  return {
    version: '1',
    sections: [{ type: 'hero', label: 'Hero' }, { type: 'section', label: 'Section' }],
    blocks: [{ type: 'heading', label: 'Heading' }],
    tokens: {},
  }
}

function context(sections: unknown = payload().sections): EditorContextData {
  return {
    owner: { type: 'page', id: 1 },
    layout: {
      version_token: 'tok-1',
      is_published_current: false,
      has_draft: true,
      has_published: false,
      draft_payload: { schema: 'pagebuilder/01', sections },
      published_payload: null,
    },
    metadata: metadata(),
    permissions: { can_publish: true },
  } as unknown as EditorContextData
}

function scene(sections?: unknown) {
  setActivePinia(createPinia())
  const store = useEditorStore()
  const client = {
    getContext: vi.fn(),
    runCommand: vi.fn(async (p: LayoutPayload, _command: string, params: Record<string, unknown>) => {
      const next = JSON.parse(JSON.stringify(p)) as { sections: Array<Record<string, unknown>> }
      if (_command === 'update-component-data') {
        const target = next.sections.find((s) => s.id === params.component_id)
        if (target) target.data = { ...(target.data as object), ...(params.data as object) }
      }
      return { payload: next, selection: { selected: (params.component_id as string) ?? null } }
    }),
    saveDraft: vi.fn(async (_payload: LayoutPayload, _token: string | null) => ({ version_token: 'tok-2', is_published_current: false, has_draft: true, has_published: false }) as never),
    publish: vi.fn(async () => ({ version_token: 'tok-3', is_published_current: true, has_draft: true, has_published: true }) as never),
  }
  ;(store as unknown as { client: unknown }).client = client
  store.applyContext(context(sections))
  return { store, client }
}

beforeEach(() => {
  vi.useFakeTimers()
})
afterEach(() => {
  useEditorStore().teardownUnloadGuard?.()
  vi.useRealTimers()
})

describe('saving unrelated edits while a provider is unavailable', () => {
  it('saves an unrelated edit and sends the unavailable subtree unchanged', async () => {
    const { store, client } = scene()
    expect(await store.updateComponentDataById('h-top', { title: 'Edited' })).toBe(true)
    expect(await store.saveDraft()).toBe(true)
    expect(client.saveDraft).toHaveBeenCalledTimes(1)
    const sent = client.saveDraft.mock.calls[0][0] as unknown as LayoutPayload
    expect(sent.sections[1]).toEqual(payload().sections[1])
    expect((sent.sections[2] as unknown as { blocks: unknown[] }).blocks[1]).toEqual((payload().sections[2] as unknown as { blocks: unknown[] }).blocks[1])
  })

  it('still refuses to save a layout holding a bare unknown type', async () => {
    const sections = [...payload().sections, { id: 'g', type: 'ghost-widget', data: {}, blocks: [] }]
    const { store, client } = scene(sections)
    expect(store.layoutIssues().map((i) => i.code)).toContain('unknown-type')
    store.dirty = true
    expect(await store.saveDraft()).toBe(false)
    expect(client.saveDraft).not.toHaveBeenCalled()
  })
})

describe('the unavailable subtree is offered no change', () => {
  it('classifies frozen nodes from the registry, not from node data', () => {
    const { store } = scene()
    for (const id of ['p-sec', 'p-inner-head', 't-badge']) expect(store.isNodeFrozen(id)).toBe(true)
    for (const id of ['h-top', 's-plain', 'b1', 'b2']) expect(store.isNodeFrozen(id)).toBe(false)
    expect(store.isNodeLocked('p-inner-head')).toBe(true)
  })

  it('refuses data edits inside the frozen subtree without a command', async () => {
    const { store, client } = scene()
    expect(await store.updateComponentDataById('p-inner-head', { text: 'x' })).toBe(false)
    expect(await store.updateComponentDataById('p-sec', { heading: 'x' })).toBe(false)
    expect(await store.renameComponent('t-badge', 'Renamed')).toBe(false)
    expect(client.runCommand).not.toHaveBeenCalled()
  })

  it('refuses delete / duplicate / cut of a frozen node or of a parent holding one', async () => {
    const { store, client } = scene()
    for (const id of ['p-sec', 't-badge', 's-plain', 'p-inner-head']) {
      await store.deleteComponent(id)
      await store.duplicateComponent(id)
      await store.cutComponent(id)
    }
    expect(client.runCommand).not.toHaveBeenCalled()
    expect(store.clipboard).toBeNull()
  })

  it('allows deleting a known sibling of a frozen node', async () => {
    const { store, client } = scene()
    await store.deleteComponent('b1')
    expect(client.runCommand).toHaveBeenCalledTimes(1)
    expect(client.runCommand.mock.calls[0].slice(1, 3)).toEqual(['remove-component', { component_id: 'b1' }])
  })

  it('never copies frozen content and never pastes a copy holding an unavailable type', async () => {
    const { store, client } = scene()
    store.copyComponent('t-badge')
    expect(store.clipboard).toBeNull()
    store.clipboard = { mode: 'copy', node: payload().sections[1], kind: 'section' }
    await store.pasteInto('h-top')
    expect(client.runCommand).not.toHaveBeenCalled()
    expect(store.commandError).toBeTruthy()
  })

  it('blocks a drag that would carry a sibling across a frozen node, allows one that does not', () => {
    const { store } = scene()
    const move = (sourceId: string, targetId: string, action: 'before' | 'after', parentId: string | null) =>
      store.dropBlocked({ mode: 'move', sourceId, sourceType: 'heading', targetId, targetType: 'heading', parentId, action })
    expect(move('b1', 'b2', 'after', 's-plain')).toBe(true)
    expect(move('b2', 'b1', 'before', 's-plain')).toBe(true)
    expect(store.dropBlocked({ mode: 'move', sourceId: 'h-top', sourceType: 'hero', targetId: 's-plain', targetType: 'section', parentId: null, action: 'after' })).toBe(true)
    expect(move('b1', 'b1', 'before', 's-plain')).toBe(false)
    expect(store.dropBlocked({ mode: 'move', sourceId: 'b1', sourceType: 'heading', targetId: 'h-top', targetType: 'hero', parentId: 'h-top', action: 'inside' })).toBe(false)
  })

  it('blocks a menu/keyboard move across a frozen sibling, allows a move to another parent', async () => {
    const { store, client } = scene()
    expect(await store.moveComponent('b1', 's-plain', 2)).toBe(false)
    expect(await store.moveComponent('h-top', null, 2)).toBe(false)
    expect(client.runCommand).not.toHaveBeenCalled()
    await store.moveComponent('b1', 'h-top', 0)
    expect(client.runCommand).toHaveBeenCalledTimes(1)
    expect(client.runCommand.mock.calls[0][1]).toBe('move-component')
  })
})

describe('publishing waits for the provider', () => {
  it('disables publish with a reason and never calls the publish endpoint', async () => {
    const { store, client } = scene()
    expect(store.unavailableComponentIds).toEqual(['p-sec', 't-badge'])
    expect(store.publishBlockedReason).toBeTruthy()
    expect(await store.publish()).toBe(false)
    expect(client.publish).not.toHaveBeenCalled()
    expect(store.error).toBe(store.publishBlockedReason)
  })

  it('becomes editable and publishable again once the provider registers its types', async () => {
    const { store, client } = scene()
    store.metadata = {
      ...store.metadata,
      sections: [...(store.metadata?.sections ?? []), { type: 'travel-booking:tour-grid', label: 'Tours' }],
      blocks: [...(store.metadata?.blocks ?? []), { type: 'pbx4-aurora:badge', label: 'Badge' }],
    } as never
    expect(store.isNodeFrozen('p-inner-head')).toBe(false)
    expect(store.publishBlockedReason).toBeNull()
    expect(await store.publish()).toBe(true)
    expect(client.publish).toHaveBeenCalledTimes(1)
  })
})
