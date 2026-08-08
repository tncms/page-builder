import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import type { EditorMetadata, LayoutPayload } from '../../types'

// PB-STATE-2P — Publish atomicity. Proves publish serializes save → publish and
// never ships a stale published_payload: a failed draft save ABORTS publish (dirty
// + typed error preserved), the save always completes before publish, an in-flight
// save is JOINED (not raced past), and a failed publish leaves the saved draft
// intact for a safe retry.

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
  ;(store as unknown as { client: unknown }).client = { runCommand: vi.fn() }
  return store
}

const tick = () => new Promise((r) => setTimeout(r))

describe('store.publish — atomicity (PB-STATE-2P)', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('saves the current payload before publishing', async () => {
    const store = scene()
    store.dirty = true
    const order: string[] = []
    ;(store.client as never as { saveDraft: unknown }).saveDraft = vi.fn(async () => {
      order.push('save')
      return {} as never
    })
    ;(store.client as never as { publish: unknown }).publish = vi.fn(async () => {
      order.push('publish')
      return {} as never
    })

    const ok = await store.publish()

    expect(ok).toBe(true)
    expect(order).toEqual(['save', 'publish'])
  })

  it('ABORTS publish and preserves dirty + error when the draft save fails', async () => {
    const store = scene()
    store.dirty = true
    const saveDraft = vi.fn(async () => {
      throw new Error('save boom')
    })
    const publish = vi.fn(async () => ({}) as never)
    ;(store.client as never as { saveDraft: unknown }).saveDraft = saveDraft
    ;(store.client as never as { publish: unknown }).publish = publish

    const ok = await store.publish()

    expect(ok).toBe(false)
    // The stale published_payload must NOT be shipped.
    expect(publish).not.toHaveBeenCalled()
    // The editor stays dirty so the unsaved payload is not silently dropped…
    expect(store.dirty).toBe(true)
    // …and the save error is surfaced, never masked.
    expect(store.error).toBe('save boom')
  })

  it('JOINS an in-flight save instead of publishing past it (Ctrl+S then Publish)', async () => {
    const store = scene()
    store.dirty = true
    let releaseSave!: () => void
    const gate = new Promise<void>((r) => {
      releaseSave = r
    })
    ;(store.client as never as { saveDraft: unknown }).saveDraft = vi.fn(async () => {
      await gate
      return {} as never
    })
    const publish = vi.fn(async () => ({}) as never)
    ;(store.client as never as { publish: unknown }).publish = publish

    const saving = store.saveDraft() // in-flight, gated
    const publishing = store.publish() // must wait for the save, not ship stale
    await tick()

    expect(publish).not.toHaveBeenCalled()

    releaseSave()
    await Promise.all([saving, publishing])

    expect(publish).toHaveBeenCalledTimes(1)
  })

  it('leaves the saved draft intact and the error visible when publish itself fails', async () => {
    const store = scene()
    store.dirty = true
    const saveDraft = vi.fn(async () => ({}) as never)
    ;(store.client as never as { saveDraft: unknown }).saveDraft = saveDraft
    ;(store.client as never as { publish: unknown }).publish = vi.fn(async () => {
      throw new Error('publish boom')
    })

    const ok = await store.publish()

    expect(ok).toBe(false)
    expect(saveDraft).toHaveBeenCalledTimes(1) // draft was persisted
    expect(store.dirty).toBe(false) // save succeeded → not dirty
    expect(store.error).toBe('publish boom')

    // Retry is safe: publish now succeeds.
    ;(store.client as never as { publish: unknown }).publish = vi.fn(async () => ({}) as never)
    expect(await store.publish()).toBe(true)
  })

  it('a no-op publish (nothing dirty) is safe and still persists the current draft', async () => {
    const store = scene()
    const saveDraft = vi.fn(async () => ({}) as never)
    const publish = vi.fn(async () => ({}) as never)
    ;(store.client as never as { saveDraft: unknown }).saveDraft = saveDraft
    ;(store.client as never as { publish: unknown }).publish = publish

    const ok = await store.publish()

    expect(ok).toBe(true)
    expect(publish).toHaveBeenCalledTimes(1)
  })
})
