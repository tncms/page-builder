// Phase 26.9 — Style binding & component adoption.
//
// Covers the single resolver entry, the adoption set, the StyleBoundField wrapper
// (transparent without context; token binding + source indicator with it), and the
// end-to-end path through PropertyInspector for an adopted field: picking a token
// commits ONE command (one undo), and the live preview shows the resolved value.

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, flushPromises } from '@vue/test-utils'

import { resolveComponentStyleValue } from '../styleBinding/resolveComponentStyleValue'
import { isStyleBound, tokenTypeFor, emptyValueFor } from '../styleBinding/adoption'
import { StyleContextKey, type StyleContext } from '../styleBinding/styleContext'
import StyleBoundField from '../styleBinding/StyleBoundField.vue'
import { TokenRegistry } from '../designTokens/TokenRegistry'
import { GlobalStyleRegistry } from '../globalStyles/registry'
import { setActiveBreakpoint, activeBreakpoint } from '../responsive/activeBreakpoint'
import type { SchemaField } from '../schema'
import '../registerBuiltinFieldEditors'

import PropertyInspector from '../PropertyInspector.vue'
import { useEditorStore } from '../../../stores/editor'
import type { LayoutPayload, EditorMetadata } from '../../../types'

const TOKENS = { color: { primary: '#2563eb', text: '#111827' }, spacing: { sm: '0.5rem', lg: '1.5rem' } }
const tokens = () => new TokenRegistry(TOKENS)

beforeEach(() => setActiveBreakpoint('desktop'))

// ---- Single resolver entry ---------------------------------------------------

describe('resolveComponentStyleValue', () => {
  it('a local value always wins', () => {
    const r = resolveComponentStyleValue({ componentType: 'text', property: 'color', localValue: '#ff0000' }, { tokens: tokens() })
    expect(r).toEqual({ value: '#ff0000', source: 'property' })
  })

  it('resolves a local token reference to its value', () => {
    const r = resolveComponentStyleValue({ componentType: 'text', property: 'color', localValue: '{{color.primary}}' }, { tokens: tokens() })
    expect(r).toEqual({ value: '#2563eb', source: 'property' })
  })

  it('falls back to a global rule when there is no local value', () => {
    const registry = new GlobalStyleRegistry()
    registry.register({ id: 'g', scope: 'global', selector: 'text', properties: { color: '{{color.primary}}' } })
    const r = resolveComponentStyleValue({ componentType: 'text', property: 'color' }, { registry, tokens: tokens() })
    expect(r).toEqual({ value: '#2563eb', source: 'global' })
  })

  it('resolves a responsive local value by breakpoint', () => {
    const env = { desktop: '{{spacing.lg}}', mobile: '{{spacing.sm}}' }
    expect(resolveComponentStyleValue({ componentType: 'x', property: 'pad', localValue: env }, { tokens: tokens(), breakpoint: 'mobile' }).value).toBe('0.5rem')
  })
})

// ---- Adoption set ------------------------------------------------------------

describe('adoption', () => {
  const f = (type: string): SchemaField => ({ name: 'x', type } as SchemaField)
  it('adopts only color/spacing/typography', () => {
    expect(isStyleBound(f('color'))).toBe(true)
    expect(isStyleBound(f('spacing'))).toBe(true)
    expect(isStyleBound(f('typography'))).toBe(true)
    expect(isStyleBound(f('text'))).toBe(false)
    expect(tokenTypeFor(f('color'))).toBe('color')
    expect(tokenTypeFor(f('text'))).toBeNull()
    expect(emptyValueFor(f('color'))).toBe('')
    expect(emptyValueFor(f('spacing'))).toEqual({})
  })
})

// ---- StyleBoundField ---------------------------------------------------------

const colorField = { name: 'color', type: 'color', label: 'Color' } as SchemaField

function stubContext(): StyleContext {
  const reg = tokens()
  return {
    tokens: () => reg,
    breakpoint: () => activeBreakpoint.value,
    componentType: () => 'text',
    componentId: () => 'n1',
    resolve: (q) => resolveComponentStyleValue(q, { tokens: reg, breakpoint: activeBreakpoint.value }),
  }
}

function mountBound(modelValue: unknown, withContext = true) {
  // The nested TokenPicker reads tokens from the store (Phase 26.7), so give it one.
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.metadata = { tokens: TOKENS } as unknown as EditorMetadata
  return mount(StyleBoundField, {
    props: { field: colorField, modelValue },
    global: withContext ? { provide: { [StyleContextKey as symbol]: stubContext() } } : {},
  })
}

describe('StyleBoundField', () => {
  it('is transparent without a style context (renders only the editor)', async () => {
    const wrapper = mountBound('#ff0000', false)
    expect(wrapper.find('.pb-style-bound').exists()).toBe(false)
    expect(wrapper.find('.pb-inspector-color').exists()).toBe(true)
    await wrapper.find('input[type="text"]').setValue('#00ff00')
    expect(wrapper.emitted('update')!.at(-1)![0]).toBe('#00ff00') // forwards edits
  })

  it('shows a Local source badge + a token button for a literal', () => {
    const wrapper = mountBound('#ff0000')
    expect(wrapper.find('.pb-style-bound').exists()).toBe(true)
    expect(wrapper.find('.pb-style-bound__source').attributes('data-source')).toBe('local')
    expect(wrapper.find('.pb-style-bound__token-btn').exists()).toBe(true)
    expect(wrapper.find('.pb-inspector-color').exists()).toBe(true) // still editing a literal
  })

  it('opening the picker and choosing a token emits the wrapped reference + commit', async () => {
    const wrapper = mountBound('#ff0000')
    await wrapper.find('.pb-style-bound__token-btn').trigger('click')
    await wrapper.find('[data-token="color.primary"]').trigger('click')
    expect(wrapper.emitted('update')!.at(-1)![0]).toBe('{{color.primary}}')
    expect(wrapper.emitted('commit')).toBeTruthy()
  })

  it('shows a Token badge + chip when bound, and unbinds to a custom value', async () => {
    const wrapper = mountBound('{{color.primary}}')
    expect(wrapper.find('.pb-style-bound__source').attributes('data-source')).toBe('token')
    expect(wrapper.find('.pb-style-bound__chip').exists()).toBe(true)
    expect(wrapper.find('.pb-inspector-color').exists()).toBe(false) // raw editor hidden while bound
    await wrapper.find('.pb-style-bound__chip .pb-inspector-iconbtn').trigger('click')
    expect(wrapper.emitted('update')!.at(-1)![0]).toBe('') // color empty value
    expect(wrapper.emitted('commit')).toBeTruthy()
  })
})

// ---- Integration through PropertyInspector -----------------------------------

function schema(fields: unknown[]) {
  return { tabs: [{ name: 'content', label: 'Content', fields }] }
}
const metadata = {
  version: '1',
  sections: [
    { type: 'hero', label: 'Hero', schema: schema([{ name: 'bg', type: 'color', label: 'BG' }]) },
    { type: 'box', label: 'Box', schema: schema([{ name: 'pad', type: 'spacing', label: 'Padding', responsive: true }]) },
  ],
  blocks: [],
  tokens: TOKENS,
} as unknown as EditorMetadata

function setup(type: string, data: Record<string, unknown>) {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = { version: 1, sections: [{ id: 'n1', type, data, blocks: [] }] } as unknown as LayoutPayload
  store.metadata = metadata
  store.boot = { locale: 'vi' } as never
  const update = vi.fn(async (before: LayoutPayload) => ({ payload: before, selection: { selected: null } }))
  ;(store as unknown as { client: unknown }).client = { runCommand: update }
  store.selectComponent('n1')
  return { store, update }
}

describe('PropertyInspector — adopted color field', () => {
  it('renders the style-bound wrapper with a source indicator', () => {
    setup('hero', { bg: '#ffffff' })
    const wrapper = mount(PropertyInspector)
    const field = wrapper.find('.pb-inspector-field[data-field="bg"]')
    expect(field.find('.pb-style-bound').exists()).toBe(true)
    expect(field.find('.pb-style-bound__source').exists()).toBe(true)
  })

  it('picking a token commits one update-component-data with the reference', async () => {
    const { update } = setup('hero', { bg: '#ffffff' })
    const wrapper = mount(PropertyInspector)
    await wrapper.find('.pb-inspector-field[data-field="bg"] .pb-style-bound__token-btn').trigger('click')
    await wrapper.find('[data-token="color.primary"]').trigger('click')
    await wrapper.find('.pb-inspector__apply-btn.pb-editor-btn--primary').trigger('click')
    await flushPromises()

    expect(update).toHaveBeenCalledTimes(1) // one command = one undo entry
    const [, command, params] = update.mock.calls[0]
    expect(command).toBe('update-component-data')
    expect((params.data as { bg: string }).bg).toBe('{{color.primary}}')
  })

  it('live preview shows the resolved token value, not the reference', async () => {
    const { store } = setup('hero', { bg: '#ffffff' })
    const patches: unknown[] = []
    store.registerLivePatch((p) => patches.push(p.value))
    const wrapper = mount(PropertyInspector)
    await wrapper.find('.pb-inspector-field[data-field="bg"] .pb-style-bound__token-btn').trigger('click')
    await wrapper.find('[data-token="color.primary"]').trigger('click')
    expect(patches.at(-1)).toBe('#2563eb') // resolved, not '{{color.primary}}'
  })
})

describe('PropertyInspector — responsive + token compose', () => {
  it('binds a token on a breakpoint through the style-bound wrapper', async () => {
    const { update } = setup('box', { pad: {} })
    const wrapper = mount(PropertyInspector)
    const field = wrapper.find('.pb-inspector-field[data-field="pad"]')
    // Style binding lives inside the responsive wrapper.
    expect(field.find('.pb-inspector-responsive-field').exists()).toBe(true)
    expect(field.find('.pb-style-bound').exists()).toBe(true)

    await field.findAll('.pb-inspector-responsive-field__tab')[1].trigger('click') // Tablet
    await field.find('.pb-style-bound__token-btn').trigger('click')
    await field.find('[data-token="spacing.sm"]').trigger('click')

    await wrapper.find('.pb-inspector__apply-btn.pb-editor-btn--primary').trigger('click')
    await flushPromises()

    const pad = (update.mock.calls[0][2].data as { pad: Record<string, unknown> }).pad
    expect(pad.tablet).toBe('{{spacing.sm}}')
  })
})
