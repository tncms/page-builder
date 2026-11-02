import { describe, it, expect, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, flushPromises, enableAutoUnmount } from '@vue/test-utils'
import PropertyInspector from '../PropertyInspector.vue'
import { useEditorStore } from '../../../stores/editor'
import { clonePlain } from '../../../utils/dataPath'
import { LayoutConflictError } from '../../../api/client'
import type { LayoutPayload, EditorMetadata } from '../../../types'

// Overlapping Apply / autosave — every Inspector write goes through ONE serialized authority.
// A Ctrl+Enter (or Apply click, or autosave/save flush) requested while an earlier Apply is on the
// wire is never dropped: it coalesces into exactly one trailing Apply of the newest complete state.
// The fake transport holds every command until the test releases it; nothing depends on timers.

const textField = (name: string, translatable = true) => ({ name, type: 'text', label: name, translatable })
const metadata = {
  version: '1',
  sections: [
    { type: 'section', label: 'Section', schema: { tabs: [{ name: 'style', label: 'Style', fields: [{ name: 'align', type: 'text', label: 'Align' }] }] } },
    // Theme-owned (PB-XREG-4 origin) — same Inspector authority as every other definition.
    { type: 'pbx4-voyage-a:hero', label: 'Voyage hero', origin: { kind: 'theme', slug: 'pbx4-voyage-a' }, schema: { tabs: [{ name: 'content', label: 'Content', fields: [textField('heading')] }] } },
  ],
  blocks: [
    // Built-in Heading.
    { type: 'heading', label: 'Heading', schema: { tabs: [{ name: 'content', label: 'Content', fields: [textField('text')] }] } },
    // Plugin-owned component.
    { type: 'pbx-shop:product-card', label: 'Product card', origin: { kind: 'plugin', slug: 'pbx-shop' }, schema: { tabs: [{ name: 'content', label: 'Content', fields: [textField('title', false)] }] } },
  ],
  tokens: {},
} as unknown as EditorMetadata

function payload(): LayoutPayload {
  return {
    version: 1,
    sections: [
      {
        id: 'sec1',
        type: 'section',
        data: { align: 'left' },
        blocks: [
          { id: 'b-head', type: 'heading', data: { text: { vi: '' } } },
          { id: 'b-plugin', type: 'pbx-shop:product-card', data: { title: '' } },
        ],
      },
      { id: 's-theme', type: 'pbx4-voyage-a:hero', data: { heading: { vi: '' } }, blocks: [] },
    ],
  } as unknown as LayoutPayload
}

const KINDS = [
  { kind: 'built-in Heading', id: 'b-head', field: 'text', read: (v: unknown) => (v as Record<string, string>).vi },
  { kind: 'plugin component', id: 'b-plugin', field: 'title', read: (v: unknown) => v as string },
  { kind: 'theme component', id: 's-theme', field: 'heading', read: (v: unknown) => (v as Record<string, string>).vi },
] as const

function findById(node: any, id: string): any {
  for (const section of node.sections ?? []) {
    if (section.id === id) return section
    for (const block of section.blocks ?? []) if (block.id === id) return block
  }
  return null
}

const stores: Array<ReturnType<typeof useEditorStore>> = []
afterEach(() => {
  for (const s of stores.splice(0)) s.cancelAutosave()
  document.body.innerHTML = ''
  vi.restoreAllMocks()
})
enableAutoUnmount(afterEach)

interface Pending {
  id: string
  data: Record<string, unknown>
  release: () => void
  fail: (e: Error) => void
}

function setup(selectId: string, initial: LayoutPayload = payload()) {
  setActivePinia(createPinia())
  const store = useEditorStore()
  stores.push(store)
  store.currentPayload = initial
  store.metadata = metadata
  store.boot = { locale: 'vi' } as never
  vi.spyOn(store, 'scheduleAutosave').mockImplementation(() => {})
  const pending: Pending[] = []
  const runCommand = vi.fn(
    (before: LayoutPayload, command: string, params: Record<string, unknown>) =>
      new Promise((resolve, reject) => {
        const next = clonePlain(before) as any
        const node = findById(next, params.component_id as string)
        if (command === 'update-component-data' && node) node.data = params.data
        pending.push({
          id: params.component_id as string,
          data: clonePlain(params.data as Record<string, unknown>),
          release: () => resolve({ payload: next, selection: { selected: null } }),
          fail: (e) => reject(e),
        })
      }),
  )
  let n = 0
  const saved: LayoutPayload[] = []
  const saveDraft = vi.fn(async (p: LayoutPayload) => {
    saved.push(clonePlain(p))
    return { version_token: `tok-${++n}`, draft_payload: p } as never
  })
  ;(store as unknown as { client: unknown }).client = { runCommand, saveDraft }
  store.selectComponent(selectId)
  const wrapper = mount(PropertyInspector, { attachTo: document.body })
  return { store, wrapper, pending, runCommand, saveDraft, saved }
}

async function release(pending: Pending[]): Promise<void> {
  const next = pending.shift()
  if (!next) throw new Error('no command in flight')
  next.release()
  await flushPromises()
}

async function typeInto(el: HTMLInputElement, text: string): Promise<void> {
  for (const ch of text) {
    const start = el.selectionStart ?? el.value.length
    const end = el.selectionEnd ?? start
    el.value = el.value.slice(0, start) + ch + el.value.slice(end)
    el.setSelectionRange(start + 1, start + 1)
    el.dispatchEvent(new InputEvent('input', { bubbles: true, data: ch }))
    await flushPromises()
  }
}

async function ctrlEnter(el: HTMLElement): Promise<void> {
  el.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', ctrlKey: true, bubbles: true }))
  await flushPromises()
}

function input(wrapper: ReturnType<typeof mount>, field: string): HTMLInputElement {
  return wrapper.find(`.pb-inspector-field[data-field="${field}"] input`).element as HTMLInputElement
}
const applyLabel = (w: ReturnType<typeof mount>) => w.find('.pb-inspector__apply-btn--primary').text()
const stored = (store: ReturnType<typeof useEditorStore>, id: string, field: string) => findById(store.currentPayload, id).data[field]

describe('Inspector Apply coordination — overlapping requests (A, B, C, I)', () => {
  for (const k of KINDS) {
    it(`A/C/I ${k.kind}: a second Ctrl+Enter during the unresolved first Apply commits the newer text`, async () => {
      const ctx = setup(k.id)
      const el = input(ctx.wrapper, k.field)
      el.focus()
      await typeInto(el, 'one')
      await ctrlEnter(el)
      expect(ctx.pending).toHaveLength(1)
      await typeInto(el, ' two') // typed while request 1 is unresolved
      await ctrlEnter(el)
      expect(ctx.pending).toHaveLength(1) // never two concurrent writes
      // Focus, DOM identity and caret survive the in-flight request (C).
      expect(document.activeElement).toBe(el)
      expect(input(ctx.wrapper, k.field)).toBe(el)
      expect(el.selectionStart).toBe('one two'.length)
      await release(ctx.pending)
      // Exactly one trailing Apply carrying the newest complete state.
      expect(ctx.pending).toHaveLength(1)
      expect(k.read(ctx.pending[0].data[k.field])).toBe('one two')
      expect(applyLabel(ctx.wrapper)).toContain('Applying')
      await release(ctx.pending)
      expect(ctx.runCommand).toHaveBeenCalledTimes(2)
      expect(k.read(stored(ctx.store, k.id, k.field))).toBe('one two')
      expect(applyLabel(ctx.wrapper)).not.toMatch(/Apply \(\d+\)/)
      expect(ctx.store.inspectorDirty).toBe(false)
      expect(el.value).toBe('one two')
    })
  }

  it('B: three or more Apply intents coalesce into ONE trailing write of the final value', async () => {
    const ctx = setup('b-head')
    const el = input(ctx.wrapper, 'text')
    el.focus()
    await typeInto(el, 'a')
    await ctrlEnter(el)
    for (const ch of ['b', 'c', 'd']) {
      await typeInto(el, ch)
      await ctrlEnter(el)
    }
    await release(ctx.pending)
    expect(ctx.pending).toHaveLength(1)
    expect(ctx.pending[0].data.text).toEqual({ vi: 'abcd' })
    await release(ctx.pending)
    expect(ctx.pending).toHaveLength(0)
    expect(ctx.runCommand).toHaveBeenCalledTimes(2)
    expect(stored(ctx.store, 'b-head', 'text')).toEqual({ vi: 'abcd' })
  })

  it('edits during the trailing request trigger another trailing run only when Apply is requested again', async () => {
    const ctx = setup('b-head')
    const el = input(ctx.wrapper, 'text')
    el.focus()
    await typeInto(el, 'a')
    await ctrlEnter(el)
    await typeInto(el, 'b')
    await ctrlEnter(el)
    await release(ctx.pending) // request 1 lands, trailing (ab) starts
    await typeInto(el, 'c')
    await ctrlEnter(el) // requested during the trailing request
    await release(ctx.pending) // trailing (ab) lands, next trailing (abc) starts
    expect(ctx.pending).toHaveLength(1)
    expect(ctx.pending[0].data.text).toEqual({ vi: 'abc' })
    await release(ctx.pending)
    expect(ctx.runCommand).toHaveBeenCalledTimes(3)
    expect(stored(ctx.store, 'b-head', 'text')).toEqual({ vi: 'abc' })
    expect(ctx.store.inspectorDirty).toBe(false)
  })
})

describe('Inspector Apply coordination — persistence and failures (D, E, F)', () => {
  it('D: first and trailing requests succeed; a save + reload shows the newest value', async () => {
    const ctx = setup('s-theme')
    const el = input(ctx.wrapper, 'heading')
    el.focus()
    await typeInto(el, 'Hello')
    await ctrlEnter(el)
    await typeInto(el, ' world')
    await ctrlEnter(el)
    await release(ctx.pending)
    await release(ctx.pending)
    expect(await ctx.store.saveDraft()).toBe(true)
    const persisted = ctx.saved[ctx.saved.length - 1]
    expect(findById(persisted, 's-theme').data.heading).toEqual({ vi: 'Hello world' })
    ctx.wrapper.unmount()
    // "Reload": a fresh editor booted from the persisted draft.
    const again = setup('s-theme', clonePlain(persisted))
    expect(input(again.wrapper, 'heading').value).toBe('Hello world')
  })

  it('E: a failed first request keeps the newer state dirty and recoverable (no trailing write, no false clean)', async () => {
    const ctx = setup('b-head')
    const el = input(ctx.wrapper, 'text')
    el.focus()
    await typeInto(el, 'x')
    await ctrlEnter(el)
    await typeInto(el, 'y')
    await ctrlEnter(el)
    ctx.pending.shift()!.fail(new Error('Request failed (422)'))
    await flushPromises()
    expect(ctx.pending).toHaveLength(0) // bounded: a failure never auto-retries
    expect(el.value).toBe('xy')
    expect(ctx.store.inspectorDirty).toBe(true)
    expect(applyLabel(ctx.wrapper)).toMatch(/Apply \(1\)/)
    expect(ctx.wrapper.text()).toContain('Apply Failed')
    expect(stored(ctx.store, 'b-head', 'text')).toEqual({ vi: '' })
    // Recoverable: an explicit Apply commits the newest state.
    await ctrlEnter(el)
    expect(ctx.pending[0].data.text).toEqual({ vi: 'xy' })
    await release(ctx.pending)
    expect(stored(ctx.store, 'b-head', 'text')).toEqual({ vi: 'xy' })
    expect(ctx.store.inspectorDirty).toBe(false)
  })

  for (const [status, err] of [
    ['403', () => new Error('Request failed (403)')],
    ['409', () => new LayoutConflictError('This layout changed in another session.', 'page_builder.layout.version_conflict', null)],
    ['419', () => new Error('Request failed (419)')],
    ['422', () => new Error('Request failed (422)')],
  ] as const) {
    it(`F: a ${status} on the trailing request keeps the latest state unsaved and writes nothing further`, async () => {
      const ctx = setup('b-plugin')
      const el = input(ctx.wrapper, 'title')
      el.focus()
      await typeInto(el, 'p')
      await ctrlEnter(el)
      await typeInto(el, 'q')
      await ctrlEnter(el)
      await release(ctx.pending) // first lands: stored 'p'
      expect(stored(ctx.store, 'b-plugin', 'title')).toBe('p')
      ctx.pending.shift()!.fail(err())
      await flushPromises()
      expect(ctx.pending).toHaveLength(0)
      expect(ctx.runCommand).toHaveBeenCalledTimes(2)
      expect(stored(ctx.store, 'b-plugin', 'title')).toBe('p') // never overwritten / partially written
      expect(el.value).toBe('pq')
      expect(ctx.store.inspectorDirty).toBe(true)
      expect(ctx.wrapper.text()).toContain('Apply Failed')
    })
  }

  it('F: a stale-token 409 on the draft save after a coalesced Apply never overwrites or adopts server data', async () => {
    const ctx = setup('b-head')
    ctx.saveDraft.mockImplementationOnce(async () => {
      throw new LayoutConflictError('This layout changed in another session.', 'page_builder.layout.version_conflict', null)
    })
    const el = input(ctx.wrapper, 'text')
    el.focus()
    await typeInto(el, 'm')
    await ctrlEnter(el)
    await typeInto(el, 'n')
    await ctrlEnter(el)
    await release(ctx.pending)
    await release(ctx.pending)
    expect(await ctx.store.saveDraft()).toBe(false)
    expect(ctx.store.conflict).toBe(true)
    expect(stored(ctx.store, 'b-head', 'text')).toEqual({ vi: 'mn' }) // local work kept
    expect(ctx.saveDraft).toHaveBeenCalledTimes(1)
  })
})

describe('Inspector Apply coordination — selection, locale, autosave, idempotence, teardown (G, H, J, K, L)', () => {
  it('G: switching component (Discard & Continue) while a request is active never writes the queued draft anywhere', async () => {
    const ctx = setup('b-head')
    const el = input(ctx.wrapper, 'text')
    el.focus()
    await typeInto(el, 'g')
    await ctrlEnter(el)
    await typeInto(el, 'h')
    await ctrlEnter(el) // queued trailing intent
    ctx.store.selectComponent('b-plugin')
    await flushPromises()
    const discard = ctx.wrapper.findAll('button').find((b) => /Discard/i.test(b.text()))
    expect(discard).toBeTruthy()
    await discard!.trigger('click')
    await flushPromises()
    expect(ctx.store.selectedComponentId).toBe('b-plugin')
    await release(ctx.pending) // request 1 (b-head = 'g') lands
    expect(ctx.pending).toHaveLength(0) // the discarded trailing intent is never sent
    expect(ctx.runCommand).toHaveBeenCalledTimes(1)
    expect(stored(ctx.store, 'b-head', 'text')).toEqual({ vi: 'g' })
    expect(stored(ctx.store, 'b-plugin', 'title')).toBe('')
    expect(input(ctx.wrapper, 'title').value).toBe('')
  })

  it('H: a locale change while a request is active never sends the queued apply into the other locale', async () => {
    const ctx = setup('s-theme')
    const el = input(ctx.wrapper, 'heading')
    el.focus()
    await typeInto(el, 'vi1')
    await ctrlEnter(el)
    await typeInto(el, '+')
    await ctrlEnter(el)
    ;(ctx.store.boot as { locale: string }).locale = 'en'
    await flushPromises()
    await release(ctx.pending)
    expect(ctx.pending).toHaveLength(0)
    expect(ctx.runCommand).toHaveBeenCalledTimes(1)
    expect(stored(ctx.store, 's-theme', 'heading')).toEqual({ vi: 'vi1' })
  })

  it('J: autosave racing an explicit Ctrl+Enter is serialized — one trailing write, then the draft save with the newest value', async () => {
    const ctx = setup('b-head')
    const el = input(ctx.wrapper, 'text')
    el.focus()
    // An earlier committed edit leaves the layout dirty, so the debounced autosave is armed (as in the browser).
    await typeInto(el, 'r0')
    await ctrlEnter(el)
    await release(ctx.pending)
    expect(ctx.store.dirty).toBe(true)
    await typeInto(el, 'r1')
    await ctrlEnter(el)
    await typeInto(el, '-r2')
    const saving = ctx.store.runAutosave() // autosave fires while request 1 is on the wire
    await flushPromises()
    await ctrlEnter(el)
    expect(ctx.pending).toHaveLength(1)
    expect(ctx.saveDraft).not.toHaveBeenCalled() // the save waits for the Inspector authority
    await release(ctx.pending)
    expect(ctx.pending).toHaveLength(1)
    expect(ctx.pending[0].data.text).toEqual({ vi: 'r0r1-r2' })
    await release(ctx.pending)
    await saving
    await flushPromises()
    expect(ctx.runCommand).toHaveBeenCalledTimes(3)
    expect(ctx.saveDraft).toHaveBeenCalledTimes(1)
    expect(findById(ctx.saved[0], 'b-head').data.text).toEqual({ vi: 'r0r1-r2' })
    expect(ctx.store.inspectorDirty).toBe(false)
  })

  it('K: repeated identical Apply requests produce no unnecessary writes', async () => {
    const ctx = setup('b-head')
    const el = input(ctx.wrapper, 'text')
    el.focus()
    await typeInto(el, 'same')
    await ctrlEnter(el)
    await ctrlEnter(el)
    await ctrlEnter(el)
    await release(ctx.pending)
    expect(ctx.pending).toHaveLength(0)
    expect(ctx.runCommand).toHaveBeenCalledTimes(1)
    await ctrlEnter(el)
    expect(ctx.runCommand).toHaveBeenCalledTimes(1)
  })

  it('L: unmount during an active request with a queued trailing apply leaks no write, handler or late UI mutation', async () => {
    const ctx = setup('b-head')
    const el = input(ctx.wrapper, 'text')
    el.focus()
    await typeInto(el, 'u')
    await ctrlEnter(el)
    await typeInto(el, 'v')
    await ctrlEnter(el)
    const removed = vi.spyOn(window, 'removeEventListener')
    ctx.wrapper.unmount()
    expect(removed).toHaveBeenCalledWith('keydown', expect.any(Function))
    expect(ctx.store.inspectorDirty).toBe(false)
    await release(ctx.pending)
    expect(ctx.pending).toHaveLength(0)
    expect(ctx.runCommand).toHaveBeenCalledTimes(1)
    expect(ctx.store.inspectorDirty).toBe(false)
    // The store's flush bridge no longer reaches the unmounted inspector.
    expect(await ctx.store.flushInspector()).toBe(true)
    expect(ctx.runCommand).toHaveBeenCalledTimes(1)
  })
})
