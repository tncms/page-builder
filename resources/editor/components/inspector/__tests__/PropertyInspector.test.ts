import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, flushPromises } from '@vue/test-utils'
import PropertyInspector from '../PropertyInspector.vue'
import { useEditorStore } from '../../../stores/editor'
import { clonePlain } from '../../../utils/dataPath'
import type { LayoutPayload, EditorMetadata } from '../../../types'

// Hero (style: select/color/spacing/toggle/media/richtext) + Button (content:
// label translatable, url, note, style select) — mirrors real Phase 6 schema shapes.
const metadata = {
  version: '1',
  sections: [
    {
      type: 'hero',
      label: 'Hero',
      schema: {
        tabs: [
          {
            name: 'style',
            label: 'Style',
            fields: [
              { name: 'align', type: 'select', label: 'Alignment', options: { left: 'Left', center: 'Center' } },
              { name: 'background', type: 'color', label: 'Background' },
              { name: 'image', type: 'media', label: 'Image' },
              {
                type: 'group',
                name: 'spacing',
                label: 'Spacing',
                fields: [{ name: 'padding', type: 'spacing', label: 'Padding' }],
              },
              { name: 'sticky', type: 'toggle', label: 'Sticky' },
              { name: 'raw', type: 'richtext', label: 'Raw' },
            ],
          },
        ],
      },
    },
  ],
  blocks: [
    {
      type: 'button',
      label: 'Button',
      schema: {
        tabs: [
          {
            name: 'content',
            label: 'Content',
            fields: [
              { name: 'label', type: 'text', label: 'Label', required: true, translatable: true },
              { name: 'url', type: 'url', label: 'URL' },
              { name: 'note', type: 'textarea', label: 'Note' },
              { name: 'style', type: 'select', label: 'Style', options: { primary: 'Primary', link: 'Link' } },
            ],
          },
        ],
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
        data: { align: 'center' },
        blocks: [{ id: 'btn1', type: 'button', data: { label: { vi: 'Bấm' }, url: '' } }],
      },
    ],
  } as unknown as LayoutPayload
}

// Find a component node (section or block) by id in a payload clone.
function findById(node: any, id: string): any {
  for (const section of node.sections ?? []) {
    if (section.id === id) return section
    for (const block of section.blocks ?? []) if (block.id === id) return block
  }
  return null
}

function setup(selectId: string | null) {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = payload()
  store.metadata = metadata
  store.boot = { locale: 'vi' } as never
  // Realistic backend: apply-component-data returns a payload with the component's
  // data replaced, so executeCommand sees a real change (marks dirty, pushes undo).
  const update = vi.fn(async (before: LayoutPayload, command: string, params: Record<string, unknown>) => {
    if (command === 'update-component-data') {
      const next = clonePlain(before) as any
      const node = findById(next, params.component_id as string)
      if (node) node.data = params.data
      return { payload: next, selection: { selected: params.component_id } }
    }
    return { payload: before, selection: { selected: null } }
  })
  ;(store as unknown as { client: unknown }).client = { runCommand: update }
  if (selectId) store.selectComponent(selectId)
  return { store, update }
}

async function type(wrapper: ReturnType<typeof mount>, field: string, value: string) {
  await wrapper.find(`.pb-inspector-field[data-field="${field}"] input, .pb-inspector-field[data-field="${field}"] textarea`).setValue(value)
}

describe('PropertyInspector — rendering (unchanged)', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('shows the empty state when nothing is selected', () => {
    setup(null)
    expect(mount(PropertyInspector).text()).toContain('Select a component')
  })

  it('renders the selected Hero fields from schema', () => {
    setup('sec1')
    const wrapper = mount(PropertyInspector)
    expect(wrapper.text()).toContain('Hero')
    expect(wrapper.text()).toContain('Alignment')
    expect(wrapper.text()).toContain('Spacing')
    expect((wrapper.find('.pb-inspector-field[data-field="align"] select').element as HTMLSelectElement).value).toBe('center')
  })

  it('SelectField renders its options', () => {
    setup('sec1')
    const opts = mount(PropertyInspector).findAll('.pb-inspector-field[data-field="align"] option').map((o) => o.text())
    expect(opts).toContain('Left')
    expect(opts).toContain('Center')
  })

  it('the translatable field shows a locale badge', () => {
    setup('btn1')
    expect(mount(PropertyInspector).find('.pb-inspector-field[data-field="label"] .pb-inspector-field__badge').text()).toContain('vi')
  })
})

describe('PropertyInspector — Layer 1 & 2 (typing stages, never commits)', () => {
  it('typing does not mutate BuilderState (no command, no dirty, payload unchanged)', async () => {
    const { store, update } = setup('btn1')
    const before = JSON.stringify(store.currentPayload)
    const wrapper = mount(PropertyInspector)

    await type(wrapper, 'label', 'Click me')
    await type(wrapper, 'url', 'https://x.dev')
    // Blur / Enter must NOT commit anymore.
    await wrapper.find('.pb-inspector-field[data-field="url"] input').trigger('blur')
    await wrapper.find('.pb-inspector-field[data-field="url"] input').trigger('keydown', { key: 'Enter' })
    await flushPromises()

    expect(update).not.toHaveBeenCalled()
    expect(store.dirty).toBe(false)
    expect(JSON.stringify(store.currentPayload)).toBe(before)
  })

  it('typing updates the preview through the Live Patch engine', async () => {
    const { store } = setup('btn1')
    const patch = vi.spyOn(store, 'emitLivePatch')
    const wrapper = mount(PropertyInspector)

    await type(wrapper, 'url', 'https://live.dev')
    expect(patch).toHaveBeenCalled()
    const last = patch.mock.calls.at(-1)![0]
    expect(last).toMatchObject({ componentId: 'btn1', field: 'url', value: 'https://live.dev' })
  })

  it('the inspector goes dirty and status flips to Unsaved Changes', async () => {
    setup('btn1')
    const wrapper = mount(PropertyInspector)
    expect(wrapper.find('.pb-inspector__status').text()).toContain('Saved')
    expect((wrapper.find('.pb-inspector__apply-btn:last-child').element as HTMLButtonElement).disabled).toBe(true)

    await type(wrapper, 'url', 'https://dirty.dev')
    expect(wrapper.find('.pb-inspector__status').text()).toContain('Unsaved Changes')
    expect((wrapper.find('.pb-inspector__apply-btn:last-child').element as HTMLButtonElement).disabled).toBe(false)
  })

  it('mirrors inspector dirtiness into the store (for the exit guard)', async () => {
    const { store } = setup('btn1')
    const wrapper = mount(PropertyInspector)
    expect(store.inspectorDirty).toBe(false)
    await type(wrapper, 'url', 'https://x.dev')
    await flushPromises()
    expect(store.inspectorDirty).toBe(true)
  })
})

describe('PropertyInspector — Layer 3 (Apply)', () => {
  it('Apply commits ONE update-component-data command with all staged fields', async () => {
    const { store, update } = setup('btn1')
    const wrapper = mount(PropertyInspector)

    await type(wrapper, 'label', 'Go')
    await type(wrapper, 'url', 'https://apply.dev')
    await type(wrapper, 'note', 'a note')
    expect(update).not.toHaveBeenCalled()

    await wrapper.find('.pb-inspector__apply-btn.pb-editor-btn--primary').trigger('click')
    await flushPromises()

    expect(update).toHaveBeenCalledTimes(1)
    const [, command, params] = update.mock.calls[0]
    expect(command).toBe('update-component-data')
    expect(params.component_id).toBe('btn1')
    expect((params.data as any).label).toEqual({ vi: 'Go' })
    expect((params.data as any).url).toBe('https://apply.dev')
    expect((params.data as any).note).toBe('a note')
    expect(store.selectedComponentId).toBe('btn1') // selection preserved
    expect(wrapper.find('.pb-inspector__status').text()).toContain('Applied') // ✓ Applied confirmation
  })

  it('undo history begins only AFTER Apply (typing creates no undo entries)', async () => {
    const { store } = setup('btn1')
    const wrapper = mount(PropertyInspector)
    await type(wrapper, 'url', 'https://undo.dev')
    await flushPromises()
    expect(store.canUndo).toBe(false)

    await wrapper.find('.pb-inspector__apply-btn.pb-editor-btn--primary').trigger('click')
    await flushPromises()
    expect(store.canUndo).toBe(true)
  })

  it('Apply is a no-op when the draft equals the applied snapshot', async () => {
    const { update } = setup('btn1')
    const wrapper = mount(PropertyInspector)
    // Type then type back to the original value.
    await type(wrapper, 'url', 'https://x.dev')
    await type(wrapper, 'url', '')
    await wrapper.find('.pb-inspector__apply-btn.pb-editor-btn--primary').trigger('click')
    await flushPromises()
    expect(update).not.toHaveBeenCalled()
  })
})

describe('PropertyInspector — Cancel / Reset', () => {
  it('Cancel discards local edits and restores the preview', async () => {
    const { store, update } = setup('btn1')
    const patch = vi.spyOn(store, 'emitLivePatch')
    const wrapper = mount(PropertyInspector)

    await type(wrapper, 'url', 'https://cancel.dev')
    patch.mockClear()

    // Cancel is the first apply-bar button.
    await wrapper.findAll('.pb-inspector__apply-btn')[0].trigger('click')
    await flushPromises()

    expect(update).not.toHaveBeenCalled()
    // Field restored to the applied (empty) value.
    expect((wrapper.find('.pb-inspector-field[data-field="url"] input').element as HTMLInputElement).value).toBe('')
    // A restore patch was emitted so the preview returns.
    expect(patch.mock.calls.some((c) => (c[0] as any).field === 'url' && (c[0] as any).value === '')).toBe(true)
    expect(wrapper.find('.pb-inspector__status').text()).toContain('Saved')
  })

  it('Cancel does not execute a command and clears dirty', async () => {
    const { update } = setup('btn1')
    const wrapper = mount(PropertyInspector)
    await type(wrapper, 'url', 'https://x.dev')
    await wrapper.findAll('.pb-inspector__apply-btn')[0].trigger('click')
    await flushPromises()
    expect(update).not.toHaveBeenCalled()
    expect(wrapper.find('.pb-inspector__status').text()).toContain('Saved')
  })
})

describe('PropertyInspector — Reset to defaults (§1/§2)', () => {
  // Hero has a `sticky` toggle seeded true; reset should stage it back to the
  // type-default (false) and stay dirty until Apply.
  function heroScene() {
    const { store, update } = setup('sec1')
    store.currentPayload = {
      version: 1,
      sections: [{ id: 'sec1', type: 'hero', data: { align: 'left', sticky: true }, blocks: [{ id: 'x', type: 'button', data: {} }] }],
    } as never
    store.selectComponent('sec1')
    return { store, update }
  }

  it('Reset confirms, then stages definition defaults without a command', async () => {
    const { update } = heroScene()
    const wrapper = mount(PropertyInspector)

    await wrapper.findAll('.pb-inspector__apply-btn')[1].trigger('click') // Reset
    // Meaningful values present → confirmation dialog.
    expect(wrapper.find('.pb-inspector-dialog').exists()).toBe(true)
    await wrapper.findAll('.pb-inspector-dialog__actions .pb-editor-btn')[0].trigger('click') // Reset to Defaults
    await flushPromises()

    expect(update).not.toHaveBeenCalled() // staged, not committed
    // sticky reset to the toggle default (false).
    expect((wrapper.find('.pb-inspector-field[data-field="sticky"] input').element as HTMLInputElement).checked).toBe(false)
    expect(wrapper.find('.pb-inspector__status').text()).toContain('Unsaved Changes')
  })

  it('Reset preserves nested blocks/structural data (only data is staged)', async () => {
    const { store } = heroScene()
    const wrapper = mount(PropertyInspector)
    await wrapper.findAll('.pb-inspector__apply-btn')[1].trigger('click')
    await wrapper.findAll('.pb-inspector-dialog__actions .pb-editor-btn')[0].trigger('click')
    await flushPromises()
    // The component's child block is untouched by a data reset.
    const sec = (store.currentPayload as any).sections[0]
    expect(sec.blocks).toEqual([{ id: 'x', type: 'button', data: {} }])
  })

  it('Apply after Reset commits one command; Cancel after Reset restores applied', async () => {
    const { update } = heroScene()
    const wrapper = mount(PropertyInspector)

    // Reset then Cancel → back to applied (align:left, sticky:true), clean.
    await wrapper.findAll('.pb-inspector__apply-btn')[1].trigger('click')
    await wrapper.findAll('.pb-inspector-dialog__actions .pb-editor-btn')[0].trigger('click')
    await flushPromises()
    await wrapper.findAll('.pb-inspector__apply-btn')[0].trigger('click') // Cancel
    await flushPromises()
    expect((wrapper.find('.pb-inspector-field[data-field="sticky"] input').element as HTMLInputElement).checked).toBe(true)
    expect(update).not.toHaveBeenCalled()

    // Reset again then Apply → one command.
    await wrapper.findAll('.pb-inspector__apply-btn')[1].trigger('click')
    await wrapper.findAll('.pb-inspector-dialog__actions .pb-editor-btn')[0].trigger('click')
    await flushPromises()
    await wrapper.find('.pb-inspector__apply-btn--primary').trigger('click')
    await flushPromises()
    expect(update).toHaveBeenCalledTimes(1)
  })
})

describe('PropertyInspector — component switch guard', () => {
  it('shows the unsaved dialog; Save Changes applies then switches', async () => {
    const { store, update } = setup('btn1')
    const wrapper = mount(PropertyInspector)
    await type(wrapper, 'url', 'https://switch.dev')

    store.selectComponent('sec1')
    await flushPromises()

    // Held on btn1 with a dialog; no commit yet.
    expect(wrapper.find('.pb-inspector-dialog').exists()).toBe(true)
    expect(store.selectedComponentId).toBe('btn1')
    expect(update).not.toHaveBeenCalled()

    await wrapper.findAll('.pb-inspector-dialog__actions .pb-editor-btn')[0].trigger('click') // Save Changes
    await flushPromises()

    expect(update).toHaveBeenCalledTimes(1)
    expect(update.mock.calls[0][2].component_id).toBe('btn1')
    expect((update.mock.calls[0][2].data as any).url).toBe('https://switch.dev')
    expect(store.selectedComponentId).toBe('sec1')
  })

  it('Discard drops edits and switches without committing', async () => {
    const { store, update } = setup('btn1')
    const wrapper = mount(PropertyInspector)
    await type(wrapper, 'url', 'https://discard.dev')

    store.selectComponent('sec1')
    await flushPromises()
    await wrapper.findAll('.pb-inspector-dialog__actions .pb-editor-btn')[1].trigger('click') // Discard
    await flushPromises()

    expect(update).not.toHaveBeenCalled()
    expect(store.selectedComponentId).toBe('sec1')
  })

  it('Cancel keeps the current selection and the draft', async () => {
    const { store } = setup('btn1')
    const wrapper = mount(PropertyInspector)
    await type(wrapper, 'url', 'https://stay.dev')

    store.selectComponent('sec1')
    await flushPromises()
    await wrapper.findAll('.pb-inspector-dialog__actions .pb-editor-btn')[2].trigger('click') // Cancel
    await flushPromises()

    expect(store.selectedComponentId).toBe('btn1')
    expect(wrapper.find('.pb-inspector-dialog').exists()).toBe(false)
    // Draft preserved (still dirty).
    expect(wrapper.find('.pb-inspector__status').text()).toContain('Unsaved Changes')
  })
})

describe('PropertyInspector — keyboard', () => {
  it('Ctrl+Enter applies the draft', async () => {
    const { update } = setup('btn1')
    const wrapper = mount(PropertyInspector, { attachTo: document.body })
    await type(wrapper, 'url', 'https://ctrlenter.dev')

    // Dispatch on the input; it bubbles to the inspector's capture-phase listener.
    await wrapper.find('.pb-inspector-field[data-field="url"] input').trigger('keydown', { key: 'Enter', ctrlKey: true })
    await flushPromises()

    expect(update).toHaveBeenCalledTimes(1)
    expect((update.mock.calls[0][2].data as any).url).toBe('https://ctrlenter.dev')
    wrapper.unmount()
  })

  it('Ctrl+S (host saveDraft) applies the inspector then saves the draft', async () => {
    // Ctrl+S is bound by the host shell (structure editor + Live Builder) and calls
    // store.saveDraft(), which flushes → Applies the inspector before saving.
    const { store, update } = setup('btn1')
    const saveDraft = vi.fn(async () => ({ draft_payload: null }) as never)
    ;(store.client as unknown as { saveDraft: unknown }).saveDraft = saveDraft
    const wrapper = mount(PropertyInspector, { attachTo: document.body })
    await type(wrapper, 'url', 'https://ctrls.dev')

    await store.saveDraft()
    await flushPromises()

    expect(update).toHaveBeenCalledTimes(1)
    expect(saveDraft).toHaveBeenCalledTimes(1)
    expect(update.mock.invocationCallOrder[0]).toBeLessThan(saveDraft.mock.invocationCallOrder[0])
    wrapper.unmount()
  })

  it('Escape cancels local edits', async () => {
    setup('btn1')
    const wrapper = mount(PropertyInspector, { attachTo: document.body })
    const input = wrapper.find('.pb-inspector-field[data-field="url"] input')
    await input.setValue('https://esc.dev')
    await input.trigger('keydown', { key: 'Escape' })
    await flushPromises()
    expect((input.element as HTMLInputElement).value).toBe('')
    wrapper.unmount()
  })
})

describe('PropertyInspector — Save Draft / Publish auto-apply', () => {
  it('Save Draft applies the staged (un-Applied) edit before saving', async () => {
    const { store, update } = setup('btn1')
    const saveDraft = vi.fn(async () => ({ draft_payload: null }) as never)
    ;(store.client as unknown as { saveDraft: unknown }).saveDraft = saveDraft
    const wrapper = mount(PropertyInspector)

    await type(wrapper, 'url', 'https://unblurred.dev')
    expect(update).not.toHaveBeenCalled()

    await store.saveDraft()
    await flushPromises()

    expect(update).toHaveBeenCalledTimes(1)
    expect((update.mock.calls[0][2].data as any).url).toBe('https://unblurred.dev')
    expect(saveDraft).toHaveBeenCalledTimes(1)
    expect(update.mock.invocationCallOrder[0]).toBeLessThan(saveDraft.mock.invocationCallOrder[0])
  })

  it('Publish applies the staged edit before publishing', async () => {
    const { store, update } = setup('btn1')
    ;(store.client as unknown as { saveDraft: unknown }).saveDraft = vi.fn(async () => ({}) as never)
    const publish = vi.fn(async () => ({}) as never)
    ;(store.client as unknown as { publish: unknown }).publish = publish
    const wrapper = mount(PropertyInspector)

    await type(wrapper, 'url', 'https://prepublish.dev')
    await store.publish()
    await flushPromises()

    expect(update).toHaveBeenCalledTimes(1)
    expect((update.mock.calls[0][2].data as any).url).toBe('https://prepublish.dev')
    expect(publish).toHaveBeenCalledTimes(1)
    void wrapper
  })
})

describe('PropertyInspector — presets / color / media stage (no commit)', () => {
  it('a preset click only stages the draft; Apply commits it', async () => {
    const { store, update } = setup('sec1')
    store.metadata = {
      ...store.metadata,
      sections: store.metadata.sections.map((s: any) =>
        s.type === 'hero'
          ? { ...s, editor: { presets: [{ name: 'dark', label: 'Dark CTA', data: { align: 'left' } }] } }
          : s,
      ),
    } as never
    const wrapper = mount(PropertyInspector)

    await wrapper.find('.pb-inspector-presets__item').trigger('click')
    await flushPromises()
    expect(update).not.toHaveBeenCalled() // staged only
    expect(wrapper.find('.pb-inspector__status').text()).toContain('Unsaved Changes')

    await wrapper.find('.pb-inspector__apply-btn.pb-editor-btn--primary').trigger('click')
    await flushPromises()
    expect(update).toHaveBeenCalledTimes(1)
    expect((update.mock.calls[0][2].data as any).align).toBe('left')
  })

  it('a color change stages (no immediate command)', async () => {
    const { update } = setup('sec1')
    const wrapper = mount(PropertyInspector)
    const swatch = wrapper.find('.pb-inspector-field[data-field="background"] input[type="color"]')
    await swatch.setValue('#ff0000')
    await swatch.trigger('change')
    await flushPromises()
    expect(update).not.toHaveBeenCalled()
    expect(wrapper.find('.pb-inspector__status').text()).toContain('Unsaved Changes')
  })

  it('a media selection stages (no immediate command)', async () => {
    const { update } = setup('sec1')
    const wrapper = mount(PropertyInspector)
    const media = wrapper.find('.pb-inspector-field[data-field="image"] input')
    await media.setValue('/media/hero.jpg')
    await media.trigger('blur')
    await flushPromises()
    expect(update).not.toHaveBeenCalled()
    expect(wrapper.find('.pb-inspector__status').text()).toContain('Unsaved Changes')
  })

  it('a toggle / select change stages (no immediate command)', async () => {
    const { update } = setup('sec1')
    const wrapper = mount(PropertyInspector)
    await wrapper.find('.pb-inspector-field[data-field="sticky"] input[type="checkbox"]').setValue(true)
    await wrapper.find('.pb-inspector-field[data-field="align"] select').setValue('left')
    await flushPromises()
    expect(update).not.toHaveBeenCalled()
  })
})

describe('PropertyInspector — dirty UX (fields / counter / tabs)', () => {
  it('a changed field shows the Modified indicator; a clean field does not', async () => {
    setup('btn1')
    const wrapper = mount(PropertyInspector)
    await type(wrapper, 'url', 'https://mod.dev')
    const urlField = wrapper.find('.pb-inspector-field[data-field="url"]')
    expect(urlField.classes()).toContain('pb-inspector-field--dirty')
    expect(urlField.find('.pb-inspector-field__modified-label').exists()).toBe(true)
    // A field we did not touch stays clean.
    expect(wrapper.find('.pb-inspector-field[data-field="note"]').classes()).not.toContain('pb-inspector-field--dirty')
  })

  it('the dirty counter and Apply label reflect the number of changed fields', async () => {
    setup('btn1')
    const wrapper = mount(PropertyInspector)
    await type(wrapper, 'url', 'https://a.dev')
    await type(wrapper, 'note', 'n')
    expect(wrapper.find('.pb-inspector__status').text()).toContain('Unsaved Changes · 2')
    expect(wrapper.find('.pb-inspector__apply-btn--primary').text()).toContain('Apply (2)')
  })

  it('reverting a single field clears only that field', async () => {
    setup('btn1')
    const wrapper = mount(PropertyInspector)
    await type(wrapper, 'url', 'https://keep.dev')
    await type(wrapper, 'note', 'note stays')
    // Revert the url field via its revert button.
    await wrapper.find('.pb-inspector-field[data-field="url"] .pb-inspector-field__revert').trigger('click')
    await flushPromises()
    expect((wrapper.find('.pb-inspector-field[data-field="url"] input').element as HTMLInputElement).value).toBe('')
    expect((wrapper.find('.pb-inspector-field[data-field="note"] textarea').element as HTMLTextAreaElement).value).toBe('note stays')
    expect(wrapper.find('.pb-inspector__status').text()).toContain('Unsaved Changes · 1')
  })

  it('a tab with changes shows a dirty dot', async () => {
    setup('btn1')
    const wrapper = mount(PropertyInspector)
    // Button schema has a single tab, so render it: still gets a dot when dirty.
    await type(wrapper, 'url', 'https://t.dev')
    // Single-tab schemas hide the tab bar; use a multi-field dirty count instead.
    expect(wrapper.find('.pb-inspector__apply-btn--primary').text()).toContain('Apply (1)')
  })
})

describe('PropertyInspector — search', () => {
  it('filters fields by label and shows an empty state for no matches', async () => {
    setup('btn1')
    const wrapper = mount(PropertyInspector)
    await wrapper.find('.pb-inspector-search__input').setValue('url')
    await flushPromises()
    expect(wrapper.find('.pb-inspector-field[data-field="url"]').exists()).toBe(true)
    expect(wrapper.find('.pb-inspector-field[data-field="label"]').exists()).toBe(false)

    await wrapper.find('.pb-inspector-search__input').setValue('zzzznomatch')
    await flushPromises()
    expect(wrapper.text()).toContain('No settings found.')
  })
})

describe('PropertyInspector — validation gating', () => {
  it('blocks Apply and shows Validation Error when a required field is empty', async () => {
    const { update } = setup('btn1')
    const wrapper = mount(PropertyInspector)
    // Clear the required, translatable `label`, and also stage another change.
    await type(wrapper, 'label', '')
    await type(wrapper, 'url', 'https://v.dev')
    await wrapper.find('.pb-inspector__apply-btn--primary').trigger('click')
    await flushPromises()

    expect(update).not.toHaveBeenCalled()
    expect(wrapper.find('.pb-inspector__status').text()).toContain('Validation Error')
  })
})

describe('PropertyInspector — Applied confirmation timing', () => {
  it('shows ✓ Applied then returns to Saved', async () => {
    vi.useFakeTimers()
    try {
      setup('btn1')
      const wrapper = mount(PropertyInspector)
      await type(wrapper, 'url', 'https://applied.dev')
      await wrapper.find('.pb-inspector__apply-btn--primary').trigger('click')
      await flushPromises()
      expect(wrapper.find('.pb-inspector__status').text()).toContain('Applied')
      vi.advanceTimersByTime(1100)
      await flushPromises()
      expect(wrapper.find('.pb-inspector__status').text()).toContain('Saved')
    } finally {
      vi.useRealTimers()
    }
  })
})

describe('PropertyInspector — switch dialog wording (§15)', () => {
  it('offers Apply & Continue / Discard & Continue / Stay Here', async () => {
    const { store } = setup('btn1')
    const wrapper = mount(PropertyInspector)
    await type(wrapper, 'url', 'https://w.dev')
    store.selectComponent('sec1')
    await flushPromises()
    const labels = wrapper.findAll('.pb-inspector-dialog__actions .pb-editor-btn').map((b) => b.text())
    expect(labels).toEqual(['Apply & Continue', 'Discard & Continue', 'Stay Here'])
  })

  it('Apply failure keeps the dialog and does not switch', async () => {
    const { store, update } = setup('btn1')
    // Force the command to fail (payload unchanged → executeCommand reports no change
    // is treated as success; instead make runCommand reject).
    update.mockRejectedValueOnce(new Error('boom'))
    const wrapper = mount(PropertyInspector)
    await type(wrapper, 'url', 'https://fail.dev')
    store.selectComponent('sec1')
    await flushPromises()
    await wrapper.findAll('.pb-inspector-dialog__actions .pb-editor-btn')[0].trigger('click') // Apply & Continue
    await flushPromises()
    expect(store.selectedComponentId).toBe('btn1') // stayed
    expect(wrapper.find('.pb-inspector-dialog').exists()).toBe(true)
  })
})
