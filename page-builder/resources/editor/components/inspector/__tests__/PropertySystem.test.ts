// Phase 26.4 — Property System foundation: editor registry, validation layer, and
// the responsive editor. Locks the normalization + the one real fix (Hero's
// `responsive` padding no longer rendering as "Unsupported").

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, flushPromises } from '@vue/test-utils'
import PropertyInspector from '../PropertyInspector.vue'
import { useEditorStore } from '../../../stores/editor'
import type { LayoutPayload, EditorMetadata } from '../../../types'
import {
  registerFieldEditor,
  resolveFieldEditor,
  isFieldEditorRegistered,
  registeredFieldTypes,
} from '../fieldEditorRegistry'
import { validateField, isFieldValid } from '../fieldValidation'
import type { SchemaField } from '../schema'
// Ensures built-ins are registered when this file runs in isolation.
import '../registerBuiltinFieldEditors'

// ---- Property Editor Registry ------------------------------------------------

describe('Property Editor Registry', () => {
  it('has every built-in editor registered (10 base + responsive)', () => {
    for (const type of ['text', 'textarea', 'toggle', 'select', 'number', 'color', 'url', 'media', 'spacing', 'typography', 'responsive']) {
      expect(isFieldEditorRegistered(type), `${type} editor`).toBe(true)
    }
    expect(registeredFieldTypes().length).toBeGreaterThanOrEqual(11)
  })

  it('flags editors that need the schema field (select/number/responsive)', () => {
    expect(resolveFieldEditor('select')?.needsField).toBe(true)
    expect(resolveFieldEditor('number')?.needsField).toBe(true)
    expect(resolveFieldEditor('responsive')?.needsField).toBe(true)
    expect(resolveFieldEditor('text')?.needsField).toBeFalsy()
  })

  it('resolves null for an unknown type and allows registering a custom editor', () => {
    expect(resolveFieldEditor('nope-xyz')).toBeNull()
    const Dummy = { name: 'Dummy', render: () => null }
    registerFieldEditor('custom-xyz', { component: Dummy })
    expect(resolveFieldEditor('custom-xyz')?.component).toBe(Dummy)
  })
})

// ---- Validation layer --------------------------------------------------------

describe('Property validation', () => {
  const f = (over: Partial<SchemaField>): SchemaField => ({ name: 'x', type: 'text', ...over } as SchemaField)

  it('requires only required fields; empty optional is valid', () => {
    expect(validateField(f({ required: true }), '')).toBe('This field is required.')
    expect(validateField(f({ required: false }), '')).toBeNull()
    expect(validateField(f({ required: true }), 'ok')).toBeNull()
  })

  it('validates URLs permissively', () => {
    expect(isFieldValid(f({ type: 'url' }), '/go')).toBe(true)
    expect(isFieldValid(f({ type: 'url' }), 'https://x.com')).toBe(true)
    expect(isFieldValid(f({ type: 'url' }), 'example.com/a')).toBe(true)
    expect(isFieldValid(f({ type: 'url' }), 'not a url')).toBe(false)
  })

  it('validates numbers with optional min/max from editor metadata', () => {
    expect(validateField(f({ type: 'number' }), 'abc')).toBe('Enter a number.')
    expect(validateField(f({ type: 'number', editor: { min: 1 } }), 0)).toBe('Must be at least 1.')
    expect(validateField(f({ type: 'number', editor: { max: 5 } }), 9)).toBe('Must be at most 5.')
    expect(validateField(f({ type: 'number', editor: { min: 1, max: 5 } }), 3)).toBeNull()
  })
})

// ---- Integration: responsive editor + apply gating ---------------------------

function schema(fields: unknown[]) {
  return { tabs: [{ name: 'content', label: 'Content', fields }] }
}

const metadata = {
  version: '1',
  sections: [
    {
      type: 'hero',
      label: 'Hero',
      schema: schema([
        {
          name: 'padding',
          type: 'responsive',
          label: 'Padding',
          responsive: true,
          field: { name: 'padding', type: 'spacing', label: 'Padding' },
        },
      ]),
    },
  ],
  blocks: [
    { type: 'button', label: 'Button', schema: schema([{ name: 'url', type: 'url', label: 'URL' }]) },
  ],
  tokens: {},
} as unknown as EditorMetadata

function payloadWith(node: { id: string; type: string; kind: 'section' | 'block'; data: Record<string, unknown> }): LayoutPayload {
  if (node.kind === 'section') {
    return { version: 1, sections: [{ ...node, blocks: [] }] } as unknown as LayoutPayload
  }
  return { version: 1, sections: [{ id: 's1', type: 'hero', data: {}, blocks: [node] }] } as unknown as LayoutPayload
}

function setup(node: { id: string; type: string; kind: 'section' | 'block'; data: Record<string, unknown> }) {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = payloadWith(node)
  store.metadata = metadata
  store.boot = { locale: 'vi' } as never
  const update = vi.fn(async (before: LayoutPayload) => ({ payload: before, selection: { selected: null } }))
  ;(store as unknown as { client: unknown }).client = { runCommand: update }
  store.selectComponent(node.id)
  return { store, update }
}

describe('responsive field is supported (was Unsupported before Phase 26.4)', () => {
  it('renders the wrapped editor, not the Unsupported fallback', () => {
    setup({ id: 'hero1', type: 'hero', kind: 'section', data: {} })
    const wrapper = mount(PropertyInspector)
    const field = wrapper.find('.pb-inspector-field[data-field="padding"]')
    expect(field.exists()).toBe(true)
    // The responsive wrapper mounts its inner editor and the foundation note.
    expect(field.find('.pb-inspector-responsive').exists()).toBe(true)
    expect(field.text()).not.toMatch(/unsupported/i)
    expect(field.text()).toMatch(/all breakpoints/i)
  })
})

describe('validation gates Apply (invalid values never commit)', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('blocks the update command when a URL is invalid, then allows it once fixed', async () => {
    const { update } = setup({ id: 'b1', type: 'button', kind: 'block', data: { url: '' } })
    const wrapper = mount(PropertyInspector)
    const urlInput = wrapper.find('.pb-inspector-field[data-field="url"] input')

    await urlInput.setValue('not a url')
    await wrapper.find('.pb-inspector__apply-btn.pb-editor-btn--primary').trigger('click')
    await flushPromises()
    expect(update).not.toHaveBeenCalled() // gated — stored data untouched
    expect(wrapper.find('.pb-inspector-field[data-field="url"] .pb-inspector-warn').exists()).toBe(true)

    await urlInput.setValue('/go')
    await wrapper.find('.pb-inspector__apply-btn.pb-editor-btn--primary').trigger('click')
    await flushPromises()
    expect(update).toHaveBeenCalledTimes(1)
    const [, command, params] = update.mock.calls[0]
    expect(command).toBe('update-component-data')
    expect((params.data as { url: string }).url).toBe('/go')
  })
})
