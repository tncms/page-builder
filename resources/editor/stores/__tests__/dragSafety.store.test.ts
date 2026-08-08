import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import { useNavigatorLocksStore } from '../navigatorLocks'
import { describeDropIntent } from '../../utils/dropIntent'
import type { EditorMetadata, LayoutPayload } from '../../types'
import type { DragSubject, DropResolution } from '../../drag/types'

// Drag safety wiring (Increment 7): the shared guardDrop gate (lock + nesting +
// self/descendant + inspector dirty-guard) and the save/publish layout-integrity
// gate. Proves the two gaps the audit found are closed: drag commits now await the
// dirty-guard, and a broken tree can never be persisted.

function scene() {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = {
    schema: 'x',
    sections: [
      { id: 'sec', type: 'hero', data: {}, blocks: [
        { id: 'b1', type: 'heading', data: {} },
        { id: 'b2', type: 'heading', data: {} },
        { id: 'b3', type: 'heading', data: {} },
      ] },
    ],
  } as unknown as LayoutPayload
  store.metadata = { version: '1', sections: [{ type: 'hero' }], blocks: [{ type: 'heading' }], tokens: {} } as unknown as EditorMetadata
  ;(store as unknown as { client: unknown }).client = { runCommand: vi.fn() }
  return store
}

const moveIntent = (store: ReturnType<typeof useEditorStore>) =>
  describeDropIntent({ id: 'b2', type: 'heading' }, { edge: 'after', targetId: 'b1', parentId: 'sec' }, (id) => store.nodeTypeOf(id))

const subject = (): DragSubject => ({ id: 'b2', kind: 'block', type: 'heading', label: '', icon: null, parentId: 'sec', index: 1 })
const resolution = (): DropResolution => ({ valid: true, reason: null, edge: 'after', targetId: 'b1', referenceId: 'b1', parentId: 'sec', markerRect: null })

describe('store.dropBlocked (sync lock + nesting gate)', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('allows a legal drop', () => {
    const store = scene()
    expect(store.dropBlocked(moveIntent(store))).toBe(false)
  })

  it('blocks a locked target container and a locked source node', () => {
    const store = scene()
    useNavigatorLocksStore().lock(store.owner, 'sec')
    expect(store.dropBlocked(moveIntent(store))).toBe(true)

    const store2 = scene()
    useNavigatorLocksStore().lock(store2.owner, 'b2')
    expect(store2.dropBlocked(moveIntent(store2))).toBe(true)
  })
})

describe('finishDrag honours the guard', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('does NOT commit when the inspector dirty-guard blocks', async () => {
    const store = scene()
    const spy = vi.spyOn(store, 'executeCommand').mockResolvedValue(true)
    store.setInspectorDirty(true)
    store.registerInspectorGuard(async () => false)
    store.dragState = { subject: subject(), resolution: null }
    await store.finishDrag(resolution())
    expect(spy).not.toHaveBeenCalled()
  })

  it('commits exactly one move-component on a clean valid drop', async () => {
    const store = scene()
    const spy = vi.spyOn(store, 'executeCommand').mockResolvedValue(true)
    store.dragState = { subject: subject(), resolution: null }
    await store.finishDrag(resolution())
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('move-component', { component_id: 'b2', target_parent_id: 'sec', index: 1 })
  })
})

describe('save/publish layout integrity gate', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('saveDraft aborts (no network) on a broken tree and surfaces the reason', async () => {
    const store = scene()
    ;(store.currentPayload as unknown as { sections: any[] }).sections[0].blocks[1].id = 'b1' // duplicate id
    const saveDraft = vi.fn(async () => ({}) as never)
    ;(store.client as unknown as { saveDraft: unknown }).saveDraft = saveDraft
    await store.saveDraft()
    expect(saveDraft).not.toHaveBeenCalled()
    expect(store.error).toMatch(/invalid layout/i)
  })

  it('saveDraft proceeds on a sound tree', async () => {
    const store = scene()
    const saveDraft = vi.fn(async () => ({ draft_payload: null }) as never)
    ;(store.client as unknown as { saveDraft: unknown }).saveDraft = saveDraft
    await store.saveDraft()
    expect(saveDraft).toHaveBeenCalledTimes(1)
  })

  it('publish aborts (no network) on a broken tree', async () => {
    const store = scene()
    ;(store.currentPayload as unknown as { sections: any[] }).sections[0].blocks[1].id = 'b1'
    const publish = vi.fn(async () => ({}) as never)
    ;(store.client as unknown as { publish: unknown }).publish = publish
    await store.publish()
    expect(publish).not.toHaveBeenCalled()
    expect(store.error).toMatch(/invalid layout/i)
  })
})
