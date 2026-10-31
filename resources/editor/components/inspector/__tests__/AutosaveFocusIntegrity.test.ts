import { describe, it, expect, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, flushPromises, enableAutoUnmount } from '@vue/test-utils'
import { watch } from 'vue'
import PropertyInspector from '../PropertyInspector.vue'
import { useEditorStore } from '../../../stores/editor'
import { clonePlain } from '../../../utils/dataPath'
import type { LayoutPayload, EditorMetadata } from '../../../types'

// PB-AUTOSAVE-FOCUS-R1 — keystroke integrity across Apply / autosave.
//
// The backend `update-component-data` command runs on a fresh BuilderState, so its
// response ALWAYS carries `selection.selected: null` (StateCommandController →
// BuilderState::make; the command never selects). The mock below mirrors that, and
// holds every command in flight until the test releases it, so typing can land
// while an Apply (user or autosave flush) is on the wire.

const metadata = {
  version: '1',
  sections: [{ type: 'hero', label: 'Hero', schema: { tabs: [{ name: 'style', label: 'Style', fields: [{ name: 'align', type: 'text', label: 'Align' }] }] } }],
  blocks: [
    {
      type: 'note',
      label: 'Note',
      schema: {
        tabs: [
          {
            name: 'content',
            label: 'Content',
            fields: [
              { name: 'title', type: 'text', label: 'Title', translatable: true },
              { name: 'body', type: 'textarea', label: 'Body' },
            ],
          },
        ],
      },
    },
    {
      type: 'quote',
      label: 'Quote',
      schema: { tabs: [{ name: 'content', label: 'Content', fields: [{ name: 'quote', type: 'textarea', label: 'Quote', translatable: true }] }] },
    },
    {
      type: 'text',
      label: 'Text',
      schema: {
        tabs: [
          {
            name: 'content',
            label: 'Content',
            fields: [{ name: 'content', type: 'richtext', label: 'Text', translatable: true, editor: { legacyField: 'text', maxBytes: 65536 } }],
          },
        ],
      },
    },
    {
      type: 'html',
      label: 'Custom HTML',
      schema: {
        tabs: [{ name: 'content', label: 'Content', fields: [{ name: 'html', type: 'htmlsource', label: 'HTML', translatable: true, editor: { maxBytes: 65536, maxDepth: 32 } }] }],
      },
    },
  ],
  tokens: {},
} as unknown as EditorMetadata

function payload(): LayoutPayload {
  return {
    version: 1,
    sections: [
      {
        id: 'sec1',
        type: 'hero',
        data: { align: 'left' },
        blocks: [
          { id: 'n1', type: 'note', data: { title: { vi: '' }, body: '' } },
          { id: 'q1', type: 'quote', data: { quote: { vi: '' } } },
          { id: 't1', type: 'text', data: { text: { vi: '' } } },
          { id: 'h1', type: 'html', data: { html: { vi: '' } } },
        ],
      },
    ],
  } as unknown as LayoutPayload
}

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
})
enableAutoUnmount(afterEach)

interface Pending {
  data: Record<string, unknown>
  release: () => void
}

function setup(selectId: string) {
  setActivePinia(createPinia())
  const store = useEditorStore()
  stores.push(store)
  store.currentPayload = payload()
  store.metadata = metadata
  store.boot = { locale: 'vi' } as never
  // Autosave stays ENABLED; only its debounce timer is stubbed, so the tests fire the real
  // autosave path (runAutosave → saveDraft → flushInspector) at exact moments.
  vi.spyOn(store, 'scheduleAutosave').mockImplementation(() => {})
  const pending: Pending[] = []
  const runCommand = vi.fn(
    (before: LayoutPayload, command: string, params: Record<string, unknown>) =>
      new Promise((resolve) => {
        const next = clonePlain(before) as any
        if (command === 'update-component-data') {
          const node = findById(next, params.component_id as string)
          if (node) node.data = params.data
        }
        pending.push({
          data: params.data as Record<string, unknown>,
          release: () => resolve({ payload: next, selection: { selected: null } }),
        })
      }),
  )
  let n = 0
  const saveDraft = vi.fn(async (p: LayoutPayload) => ({ version_token: `tok-${++n}`, draft_payload: p }) as never)
  ;(store as unknown as { client: unknown }).client = { runCommand, saveDraft }
  store.selectComponent(selectId)
  const wrapper = mount(PropertyInspector, { attachTo: document.body })
  return { store, wrapper, pending, runCommand, saveDraft }
}

/** Release the oldest in-flight command and let the editor settle. */
async function releaseNext(pending: Pending[]): Promise<void> {
  const next = pending.shift()
  if (!next) throw new Error('no command in flight')
  next.release()
  await flushPromises()
}

/** Type into a text control the way a browser does: change the value, then fire input. */
async function typeInto(el: HTMLInputElement | HTMLTextAreaElement, text: string, composing = false): Promise<void> {
  for (const ch of text) {
    const start = el.selectionStart ?? el.value.length
    const end = el.selectionEnd ?? start
    el.value = el.value.slice(0, start) + ch + el.value.slice(end)
    el.setSelectionRange(start + 1, start + 1)
    el.dispatchEvent(new InputEvent('input', { bubbles: true, data: ch, isComposing: composing }))
    await flushPromises()
  }
}

function applyButton(wrapper: ReturnType<typeof mount>) {
  return wrapper.find('.pb-inspector__apply-btn--primary')
}

function fieldEl<T extends Element>(wrapper: ReturnType<typeof mount>, name: string, selector: string): T {
  return wrapper.find(`.pb-inspector-field[data-field="${name}"] ${selector}`).element as T
}

/** Apply via the button, keep typing while the Apply and then an autosave flush are in flight. */
async function applyThenTypeAcrossAutosave(
  ctx: ReturnType<typeof setup>,
  el: HTMLInputElement | HTMLTextAreaElement,
) {
  el.focus()
  await typeInto(el, 'AB')
  await applyButton(ctx.wrapper).trigger('click')
  await typeInto(el, 'C') // typed while the user's Apply is on the wire
  await releaseNext(ctx.pending)
  el.focus() // the author keeps working in the field
  await typeInto(el, 'DE')
  // The debounced autosave fires: saveDraft → flushInspector → Apply of the staged draft.
  const saving = ctx.store.runAutosave()
  await flushPromises()
  await typeInto(el, 'FG') // typed while the autosave flush is on the wire
  // (Pre-fix the field was remounted after the first response, so nothing was staged
  // and the flush sent no command; release only what is actually in flight.)
  while (ctx.pending.length) await releaseNext(ctx.pending)
  await saving
  await flushPromises()
  // The autosave really persisted a draft carrying the keystrokes typed before it fired.
  expect(ctx.saveDraft).toHaveBeenCalled()
  await typeInto(el, 'H') // typed after the autosave response
}

describe('PB-AUTOSAVE-FOCUS-R1 — store selection during an Apply round-trip', () => {
  it('never clears the selection while committing component data', async () => {
    const { store, pending } = setup('n1')
    const seen: Array<string | null> = []
    const stop = watch(() => store.selectedComponentId, (v) => seen.push(v), { flush: 'sync' })
    const done = store.updateComponentDataById('n1', { title: { vi: 'x' }, body: '' })
    await flushPromises()
    await releaseNext(pending)
    expect(await done).toBe(true)
    stop()
    expect(seen).not.toContain(null)
    expect(store.selectedComponentId).toBe('n1')
  })

  it('does not drag the selection back when the author picked another component during the Apply', async () => {
    const { store, pending } = setup('n1')
    const done = store.updateComponentDataById('n1', { title: { vi: 'x' }, body: '' })
    await flushPromises()
    store.selectComponent('sec1')
    await releaseNext(pending)
    await done
    expect(store.selectedComponentId).toBe('sec1')
  })
})

describe('PB-AUTOSAVE-FOCUS-R1 — keystroke integrity across Apply and autosave', () => {
  it('plain textarea keeps DOM identity, focus, caret and every character', async () => {
    const ctx = setup('n1')
    const el = fieldEl<HTMLTextAreaElement>(ctx.wrapper, 'body', 'textarea')
    await applyThenTypeAcrossAutosave(ctx, el)
    const now = fieldEl<HTMLTextAreaElement>(ctx.wrapper, 'body', 'textarea')
    expect(now).toBe(el)
    expect(document.activeElement).toBe(el)
    expect(el.value).toBe('ABCDEFGH')
    expect(el.selectionStart).toBe(8)
    // The characters typed after the last Apply are still staged, so Apply is offered.
    expect((applyButton(ctx.wrapper).element as HTMLButtonElement).disabled).toBe(false)
    await applyButton(ctx.wrapper).trigger('click')
    await releaseNext(ctx.pending)
    expect(findById(ctx.store.currentPayload, 'n1').data.body).toBe('ABCDEFGH')
  })

  it('plain text input (translatable) keeps DOM identity, focus and every character', async () => {
    const ctx = setup('n1')
    const el = fieldEl<HTMLInputElement>(ctx.wrapper, 'title', 'input')
    await applyThenTypeAcrossAutosave(ctx, el)
    expect(fieldEl<HTMLInputElement>(ctx.wrapper, 'title', 'input')).toBe(el)
    expect(document.activeElement).toBe(el)
    expect(el.value).toBe('ABCDEFGH')
    await applyButton(ctx.wrapper).trigger('click')
    await releaseNext(ctx.pending)
    expect(findById(ctx.store.currentPayload, 'n1').data.title).toEqual({ vi: 'ABCDEFGH' })
  })

  it('legacy Quote textarea (the field that first showed the defect) keeps every character', async () => {
    const ctx = setup('q1')
    const el = fieldEl<HTMLTextAreaElement>(ctx.wrapper, 'quote', 'textarea')
    await applyThenTypeAcrossAutosave(ctx, el)
    expect(fieldEl<HTMLTextAreaElement>(ctx.wrapper, 'quote', 'textarea')).toBe(el)
    expect(document.activeElement).toBe(el)
    expect(el.value).toBe('ABCDEFGH')
    await applyButton(ctx.wrapper).trigger('click')
    await releaseNext(ctx.pending)
    expect(findById(ctx.store.currentPayload, 'q1').data.quote).toEqual({ vi: 'ABCDEFGH' })
  })

  it('Custom HTML source keeps DOM identity, focus, selection range and scroll position', async () => {
    const ctx = setup('h1')
    const el = fieldEl<HTMLTextAreaElement>(ctx.wrapper, 'html', 'textarea')
    await applyThenTypeAcrossAutosave(ctx, el)
    el.setSelectionRange(2, 5)
    el.scrollTop = 40
    const saving = ctx.store.saveDraft()
    await flushPromises()
    await releaseNext(ctx.pending)
    await saving
    await flushPromises()
    expect(fieldEl<HTMLTextAreaElement>(ctx.wrapper, 'html', 'textarea')).toBe(el)
    expect(document.activeElement).toBe(el)
    expect([el.selectionStart, el.selectionEnd]).toEqual([2, 5])
    expect(el.scrollTop).toBe(40)
    expect(el.value).toBe('ABCDEFGH')
    expect(findById(ctx.store.currentPayload, 'h1').data.html).toEqual({ vi: 'ABCDEFGH' })
  })

  it('Visual Text keeps its surface, focus, caret and every character', async () => {
    const ctx = setup('t1')
    const surface = ctx.wrapper.find('.pb-richtext__surface').element as HTMLElement
    surface.focus()
    const typeRich = async (html: string) => {
      surface.innerHTML = html
      const range = document.createRange()
      range.selectNodeContents(surface.querySelector('strong') ?? surface)
      range.collapse(false)
      window.getSelection()?.removeAllRanges()
      window.getSelection()?.addRange(range)
      surface.dispatchEvent(new InputEvent('input', { bubbles: true }))
      await flushPromises()
    }
    await typeRich('<p>A<strong>B</strong></p>')
    await applyButton(ctx.wrapper).trigger('click')
    await typeRich('<p>A<strong>BC</strong></p>')
    await releaseNext(ctx.pending)
    await typeRich('<p>A<strong>BCD</strong></p>')
    const saving = ctx.store.runAutosave()
    await flushPromises()
    await typeRich('<p>A<strong>BCDE</strong></p>')
    while (ctx.pending.length) await releaseNext(ctx.pending)
    await saving
    await flushPromises()
    expect(ctx.wrapper.find('.pb-richtext__surface').element).toBe(surface)
    expect(document.activeElement).toBe(surface)
    expect(surface.innerHTML).toBe('<p>A<strong>BCDE</strong></p>')
    const sel = window.getSelection()
    expect(sel?.anchorNode && surface.contains(sel.anchorNode)).toBe(true)
    await applyButton(ctx.wrapper).trigger('click')
    await releaseNext(ctx.pending)
    expect(findById(ctx.store.currentPayload, 't1').data.content).toEqual({ vi: '<p>A<strong>BCDE</strong></p>' })
  })

  it('an IME composition spanning the autosave is neither interrupted nor committed half-way', async () => {
    const ctx = setup('n1')
    const el = fieldEl<HTMLTextAreaElement>(ctx.wrapper, 'body', 'textarea')
    el.focus()
    await typeInto(el, 'Vi')
    await applyButton(ctx.wrapper).trigger('click')
    await releaseNext(ctx.pending)
    // Telex-style composition: "e" → "ê" → "ệ" while autosave fires.
    el.dispatchEvent(new CompositionEvent('compositionstart', { bubbles: true, data: '' }))
    await typeInto(el, 'e', true)
    const saving = ctx.store.runAutosave()
    await flushPromises()
    el.value = 'Viê'
    el.dispatchEvent(new InputEvent('input', { bubbles: true, data: 'ê', isComposing: true }))
    await flushPromises()
    while (ctx.pending.length) await releaseNext(ctx.pending)
    await saving
    await flushPromises()
    // No half-composed text was committed by the autosave.
    expect(findById(ctx.store.currentPayload, 'n1').data.body).toBe('Vi')
    el.value = 'Việ'
    el.dispatchEvent(new InputEvent('input', { bubbles: true, data: 'ệ', isComposing: true }))
    el.dispatchEvent(new CompositionEvent('compositionend', { bubbles: true, data: 'ệ' }))
    el.dispatchEvent(new InputEvent('input', { bubbles: true, data: 'ệ' }))
    await flushPromises()
    expect(fieldEl<HTMLTextAreaElement>(ctx.wrapper, 'body', 'textarea')).toBe(el)
    expect(document.activeElement).toBe(el)
    expect(el.value).toBe('Việ')
    await applyButton(ctx.wrapper).trigger('click')
    await releaseNext(ctx.pending)
    expect(findById(ctx.store.currentPayload, 'n1').data.body).toBe('Việ')
  })

  it('an explicit Save during a composition still applies the staged draft', async () => {
    const ctx = setup('n1')
    const el = fieldEl<HTMLTextAreaElement>(ctx.wrapper, 'body', 'textarea')
    el.focus()
    el.dispatchEvent(new CompositionEvent('compositionstart', { bubbles: true, data: '' }))
    await typeInto(el, 'ê', true)
    const saving = ctx.store.saveDraft()
    await flushPromises()
    while (ctx.pending.length) await releaseNext(ctx.pending)
    expect(await saving).toBe(true)
    expect(findById(ctx.saveDraft.mock.calls[0][0], 'n1').data.body).toBe('ê')
  })

  it('leaving the inspector ends a composition whose compositionend was lost', async () => {
    const ctx = setup('n1')
    const el = fieldEl<HTMLTextAreaElement>(ctx.wrapper, 'body', 'textarea')
    el.focus()
    el.dispatchEvent(new CompositionEvent('compositionstart', { bubbles: true, data: '' }))
    await typeInto(el, 'ê', true)
    const outside = document.createElement('button')
    document.body.appendChild(outside)
    outside.focus() // focusout with relatedTarget outside the inspector; no compositionend
    await flushPromises()
    ctx.store.dirty = true
    const saving = ctx.store.runAutosave()
    await flushPromises()
    while (ctx.pending.length) await releaseNext(ctx.pending)
    await saving
    expect(findById(ctx.store.currentPayload, 'n1').data.body).toBe('ê')
  })

  it('rapid edits while an earlier Apply is in flight: the newest value wins, in order', async () => {
    const ctx = setup('n1')
    const el = fieldEl<HTMLTextAreaElement>(ctx.wrapper, 'body', 'textarea')
    el.focus()
    await typeInto(el, 'A')
    await applyButton(ctx.wrapper).trigger('click') // Apply #1 ('A') in flight
    await typeInto(el, 'B')
    const flushing = ctx.store.flushInspector() // autosave flush ('AB') while #1 is in flight
    await flushPromises()
    // Release out of order when the editor has let both out: newest first.
    if (ctx.pending.length > 1) {
      const newest = ctx.pending.pop()!
      newest.release()
      await flushPromises()
    }
    while (ctx.pending.length) await releaseNext(ctx.pending)
    await flushing
    await flushPromises()
    expect(findById(ctx.store.currentPayload, 'n1').data.body).toBe('AB')
    expect(el.value).toBe('AB')
  })

  it('a stale command response never overwrites a newer local mutation', async () => {
    const ctx = setup('n1')
    const first = ctx.store.updateComponentDataById('n1', { title: { vi: 'old' }, body: '' })
    await flushPromises()
    const second = ctx.store.updateComponentDataById('n1', { title: { vi: 'new' }, body: '' })
    await flushPromises()
    // Whatever the editor has on the wire, the newest request answers first.
    while (ctx.pending.length) {
      ctx.pending.pop()!.release()
      await flushPromises()
    }
    await Promise.all([first, second])
    expect(findById(ctx.store.currentPayload, 'n1').data.title).toEqual({ vi: 'new' })
  })

  it('switching component while a save is in flight does not pull focus back to the old field', async () => {
    const ctx = setup('n1')
    const el = fieldEl<HTMLTextAreaElement>(ctx.wrapper, 'body', 'textarea')
    el.focus()
    await typeInto(el, 'AB')
    const saving = ctx.store.saveDraft()
    await flushPromises()
    // The author moves on: Discard the (still staged) draft and select the section.
    ctx.store.selectComponent('sec1')
    await flushPromises()
    const discard = ctx.wrapper.findAll('.pb-inspector-dialog button').find((b) => b.text().startsWith('Discard'))
    if (discard) await discard.trigger('click')
    await flushPromises()
    const other = ctx.wrapper.find('.pb-inspector-field[data-field="align"] input').element as HTMLInputElement
    other.focus()
    while (ctx.pending.length) await releaseNext(ctx.pending)
    await saving
    await flushPromises()
    expect(ctx.store.selectedComponentId).toBe('sec1')
    expect(document.activeElement).toBe(other)
    expect(ctx.wrapper.find('.pb-inspector-field[data-field="body"]').exists()).toBe(false)
  })

  it('manual Save while typing keeps the field and the characters', async () => {
    const ctx = setup('n1')
    const el = fieldEl<HTMLTextAreaElement>(ctx.wrapper, 'body', 'textarea')
    el.focus()
    await typeInto(el, 'AB')
    const saving = ctx.store.saveDraft()
    await flushPromises()
    await typeInto(el, 'C')
    await releaseNext(ctx.pending)
    expect(await saving).toBe(true)
    await flushPromises()
    expect(fieldEl<HTMLTextAreaElement>(ctx.wrapper, 'body', 'textarea')).toBe(el)
    expect(document.activeElement).toBe(el)
    expect(el.value).toBe('ABC')
    expect(ctx.saveDraft).toHaveBeenCalledTimes(1)
    expect(findById(ctx.saveDraft.mock.calls[0][0], 'n1').data.body).toBe('AB')
    expect((applyButton(ctx.wrapper).element as HTMLButtonElement).disabled).toBe(false)
  })
})
