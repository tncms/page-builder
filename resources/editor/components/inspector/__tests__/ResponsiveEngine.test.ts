// Phase 26.6 — Responsive Property Engine.
//
// Covers the pure value model, the breakpoint registry, the shared wrapper component,
// and an end-to-end path through PropertyInspector: editing per breakpoint produces a
// `{ desktop, tablet, mobile }` envelope, commits as ONE undoable command, validates
// the base value, and leaves non-responsive fields (and the legacy `type:'responsive'`
// wrapper) untouched.

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, flushPromises } from '@vue/test-utils'

import {
  isResponsiveEnvelope,
  resolveEffective,
  setBreakpoint,
  clearBreakpoint,
  hasOwnOverride,
  baseValue,
} from '../responsive/responsiveValue'
import { listBreakpoints, breakpointKeys, DEFAULT_BREAKPOINT } from '../responsive/breakpoints'
import { activeBreakpoint, setActiveBreakpoint } from '../responsive/activeBreakpoint'
import ResponsivePropertyField from '../responsive/ResponsivePropertyField.vue'
import { validateField } from '../fieldValidation'
import type { SchemaField } from '../schema'
import '../registerBuiltinFieldEditors'

import PropertyInspector from '../PropertyInspector.vue'
import { useEditorStore } from '../../../stores/editor'
import type { LayoutPayload, EditorMetadata } from '../../../types'

// Reset the shared editing breakpoint between tests.
beforeEach(() => setActiveBreakpoint(DEFAULT_BREAKPOINT))

// ---- Breakpoint registry -----------------------------------------------------

describe('breakpoints', () => {
  it('exposes desktop/tablet/mobile in order with desktop as base', () => {
    expect(listBreakpoints().map((b) => b.id)).toEqual(['desktop', 'tablet', 'mobile'])
    expect(DEFAULT_BREAKPOINT).toBe('desktop')
    expect(breakpointKeys()).toEqual(expect.arrayContaining(['desktop', 'tablet', 'mobile']))
  })
})

// ---- Pure value model (mirrors the PHP ResponsiveResolver contract) ----------

describe('responsiveValue', () => {
  it('detects only envelopes whose keys are all breakpoint ids', () => {
    expect(isResponsiveEnvelope({ desktop: '40px', tablet: '20px' })).toBe(true)
    expect(isResponsiveEnvelope({ desktop: '40px' })).toBe(true)
    expect(isResponsiveEnvelope({ top: '40px', right: '0' })).toBe(false) // composite spacing
    expect(isResponsiveEnvelope('40px')).toBe(false)
    expect(isResponsiveEnvelope({})).toBe(false)
  })

  it('resolves the effective value with an authoring cascade', () => {
    const env = { desktop: '40px', tablet: '20px' }
    expect(resolveEffective(env, 'desktop')).toBe('40px')
    expect(resolveEffective(env, 'tablet')).toBe('20px')
    expect(resolveEffective(env, 'mobile')).toBe('20px') // mobile → tablet → desktop
    expect(resolveEffective('40px', 'mobile')).toBe('40px') // plain = same everywhere
  })

  it('editing the base keeps a plain value plain; other breakpoints wrap', () => {
    expect(setBreakpoint('40px', 'desktop', '48px')).toBe('48px')
    expect(setBreakpoint('40px', 'tablet', '20px')).toEqual({ desktop: '40px', tablet: '20px' })
    expect(setBreakpoint({ desktop: '40px' }, 'desktop', '48px')).toEqual({ desktop: '48px' })
  })

  it('clearing an override collapses back to plain when only the base remains', () => {
    expect(clearBreakpoint({ desktop: '40px', tablet: '20px' }, 'tablet')).toBe('40px')
    expect(clearBreakpoint({ desktop: '40px', tablet: '20px', mobile: '10px' }, 'tablet')).toEqual({
      desktop: '40px',
      mobile: '10px',
    })
    expect(clearBreakpoint('40px', 'tablet')).toBe('40px') // no-op on plain
  })

  it('reports per-breakpoint overrides and the base value', () => {
    const env = { desktop: '40px', tablet: '20px' }
    expect(hasOwnOverride(env, 'tablet')).toBe(true)
    expect(hasOwnOverride(env, 'mobile')).toBe(false)
    expect(hasOwnOverride(env, 'desktop')).toBe(false) // base is never an "override"
    expect(baseValue(env)).toBe('40px')
    expect(baseValue('40px')).toBe('40px')
  })
})

// ---- Shared wrapper component ------------------------------------------------

const spacingField = { name: 'padding', type: 'spacing', label: 'Padding', responsive: true } as SchemaField

describe('ResponsivePropertyField', () => {
  it('renders one tab per breakpoint and the resolved inner editor', () => {
    const wrapper = mount(ResponsivePropertyField, { props: { field: spacingField, modelValue: {} } })
    expect(wrapper.findAll('.pb-inspector-responsive-field__tab').length).toBe(3)
    expect(wrapper.find('.pb-inspector-spacing').exists()).toBe(true) // inner spacing editor
  })

  it('editing on desktop keeps the value plain', async () => {
    const wrapper = mount(ResponsivePropertyField, { props: { field: spacingField, modelValue: {} } })
    await wrapper.find('.pb-inspector-spacing input').setValue('40px')
    // Spacing linked-mode emits all four sides; the wrapper keeps it a plain object.
    expect(isResponsiveEnvelope(wrapper.emitted('update')!.at(-1)![0])).toBe(false)
  })

  it('editing a non-desktop breakpoint produces an envelope override', async () => {
    const wrapper = mount(ResponsivePropertyField, {
      props: { field: spacingField, modelValue: { top: '40px', right: '40px', bottom: '40px', left: '40px' } },
    })
    setActiveBreakpoint('tablet')
    await wrapper.vm.$nextTick()
    await wrapper.find('.pb-inspector-spacing input').setValue('20px')
    const emitted = wrapper.emitted('update')!.at(-1)![0] as Record<string, unknown>
    expect(isResponsiveEnvelope(emitted)).toBe(true)
    expect(emitted.desktop).toEqual({ top: '40px', right: '40px', bottom: '40px', left: '40px' })
    expect(emitted.tablet).toEqual({ top: '20px', right: '20px', bottom: '20px', left: '20px' })
  })

  it('shows a reset control that clears the active override', async () => {
    setActiveBreakpoint('tablet')
    const wrapper = mount(ResponsivePropertyField, {
      props: { field: spacingField, modelValue: { desktop: '40px', tablet: '20px' } },
    })
    await wrapper.vm.$nextTick()
    const reset = wrapper.find('.pb-inspector-iconbtn')
    expect(reset.exists()).toBe(true)
    await reset.trigger('click')
    expect(wrapper.emitted('update')!.at(-1)![0]).toBe('40px') // collapsed back to plain base
    expect(wrapper.emitted('commit')).toBeTruthy()
  })
})

// ---- Validation --------------------------------------------------------------

describe('responsive validation uses the base value', () => {
  const f = (over: Partial<SchemaField>): SchemaField => ({ name: 'x', type: 'text', responsive: true, ...over } as SchemaField)

  it('validates the desktop base for required + typed rules', () => {
    expect(validateField(f({ required: true }), { desktop: '' })).toBe('This field is required.')
    expect(validateField(f({ required: true }), { desktop: 'ok', tablet: '' })).toBeNull()
    expect(validateField(f({ type: 'url' }), { desktop: 'not a url' })).toBe('Enter a valid URL.')
    expect(validateField(f({ type: 'url' }), { desktop: '/go', tablet: 'also-bad url' })).toBeNull()
  })
})

// ---- Integration through PropertyInspector -----------------------------------

function schema(fields: unknown[]) {
  return { tabs: [{ name: 'content', label: 'Content', fields }] }
}

const metadata = {
  version: '1',
  sections: [
    {
      type: 'hero',
      label: 'Hero',
      schema: schema([{ name: 'padding', type: 'spacing', label: 'Padding', responsive: true }]),
    },
  ],
  blocks: [],
  tokens: {},
} as unknown as EditorMetadata

function payloadWith(data: Record<string, unknown>): LayoutPayload {
  return { version: 1, sections: [{ id: 'hero1', type: 'hero', data, blocks: [] }] } as unknown as LayoutPayload
}

function setup(data: Record<string, unknown>) {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = payloadWith(data)
  store.metadata = metadata
  store.boot = { locale: 'vi' } as never
  const update = vi.fn(async (before: LayoutPayload) => ({ payload: before, selection: { selected: null } }))
  ;(store as unknown as { client: unknown }).client = { runCommand: update }
  store.selectComponent('hero1')
  return { store, update }
}

describe('PropertyInspector — responsive field end to end', () => {
  it('commits a per-breakpoint envelope as ONE update-component-data command', async () => {
    const { update } = setup({ padding: { top: '40px', right: '40px', bottom: '40px', left: '40px' } })
    const wrapper = mount(PropertyInspector)

    // Switch to Tablet and lower the padding.
    await wrapper.findAll('.pb-inspector-responsive-field__tab')[1].trigger('click')
    await wrapper.find('.pb-inspector-spacing input').setValue('20px')

    await wrapper.find('.pb-inspector__apply-btn.pb-editor-btn--primary').trigger('click')
    await flushPromises()

    expect(update).toHaveBeenCalledTimes(1) // one command → one undo entry
    const [, command, params] = update.mock.calls[0]
    expect(command).toBe('update-component-data')
    const padding = (params.data as { padding: Record<string, unknown> }).padding
    expect(isResponsiveEnvelope(padding)).toBe(true)
    expect(padding.tablet).toEqual({ top: '20px', right: '20px', bottom: '20px', left: '20px' })
    expect(padding.desktop).toEqual({ top: '40px', right: '40px', bottom: '40px', left: '40px' })
  })

  it('renders the responsive tab strip for a responsive field', () => {
    setup({ padding: '40px' })
    const wrapper = mount(PropertyInspector)
    const field = wrapper.find('.pb-inspector-field[data-field="padding"]')
    expect(field.find('.pb-inspector-responsive-field').exists()).toBe(true)
    expect(field.findAll('.pb-inspector-responsive-field__tab').length).toBe(3)
  })
})

// ---- Live-preview effective value -------------------------------------------

describe('live preview reflects the active breakpoint', () => {
  it('emits the effective value for the active breakpoint, not the envelope', async () => {
    const { store } = setup({ padding: { top: '40px', right: '40px', bottom: '40px', left: '40px' } })
    const patches: unknown[] = []
    store.registerLivePatch((p) => patches.push(p.value))
    const wrapper = mount(PropertyInspector)

    await wrapper.findAll('.pb-inspector-responsive-field__tab')[1].trigger('click') // Tablet
    await wrapper.find('.pb-inspector-spacing input').setValue('20px')

    // The last live patch carries the tablet effective (a spacing object), never the
    // {desktop,tablet} envelope.
    const last = patches.at(-1)
    expect(isResponsiveEnvelope(last)).toBe(false)
    expect(last).toEqual({ top: '20px', right: '20px', bottom: '20px', left: '20px' })
  })
})

// ---- Backward compatibility --------------------------------------------------

describe('non-responsive fields are unchanged', () => {
  afterEach(() => setActiveBreakpoint(DEFAULT_BREAKPOINT))

  it('a plain field never renders the responsive wrapper', () => {
    const md = {
      version: '1',
      sections: [{ type: 'hero', label: 'Hero', schema: schema([{ name: 'title', type: 'text', label: 'Title' }]) }],
      blocks: [],
      tokens: {},
    } as unknown as EditorMetadata
    setActivePinia(createPinia())
    const store = useEditorStore()
    store.currentPayload = payloadWith({ title: 'Hi' })
    store.metadata = md
    store.boot = { locale: 'vi' } as never
    ;(store as unknown as { client: unknown }).client = { runCommand: vi.fn() }
    store.selectComponent('hero1')
    const wrapper = mount(PropertyInspector)
    const field = wrapper.find('.pb-inspector-field[data-field="title"]')
    expect(field.find('.pb-inspector-responsive-field').exists()).toBe(false)
  })
})
