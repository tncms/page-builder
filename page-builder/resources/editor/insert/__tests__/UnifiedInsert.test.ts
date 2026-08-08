// Phase 26.37 — unified insert: a router/discovery layer. Providers only OPEN existing experiences;
// nothing here resolves, inserts, dispatches a command, or touches BuilderState.

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import {
  InsertProviderRegistry,
  InsertRegistryError,
  makeInsertProvider,
  registerBuiltinInsertProviders,
  defaultInsertOpenHandlers,
  insertProviderRegistry,
  useInsertPanelStore,
  InsertPanel,
  INSERT_CATEGORY_ORDER,
  isKnownInsertCategory,
  type InsertOpenHandlers,
} from '..'
import { usePatternPickerStore } from '../../patterns/picker/patternPickerStore'

function spyHandlers(): InsertOpenHandlers {
  return { blocks: vi.fn(), patterns: vi.fn(), templates: vi.fn(), assets: vi.fn() }
}

beforeEach(() => {
  setActivePinia(createPinia())
  insertProviderRegistry.clear()
})
afterEach(() => insertProviderRegistry.clear())

describe('InsertProviderRegistry', () => {
  it('registers, looks up, and preserves registration order', () => {
    const r = new InsertProviderRegistry()
    r.register(makeInsertProvider('blocks', () => {}))
    r.register(makeInsertProvider('assets', () => {}))
    expect(r.keys()).toEqual(['blocks', 'assets'])
    expect(r.has('blocks')).toBe(true)
    expect(r.get('assets')?.label).toBe('Assets')
    expect(r.all().map((p) => p.key)).toEqual(['blocks', 'assets'])
  })

  it('rejects a duplicate category (never overwrites)', () => {
    const r = new InsertProviderRegistry()
    r.register(makeInsertProvider('blocks', () => {}))
    expect(() => r.register(makeInsertProvider('blocks', () => {}))).toThrow(InsertRegistryError)
  })
})

describe('registerBuiltinInsertProviders', () => {
  it('registers the four categories in deterministic order', () => {
    const r = new InsertProviderRegistry()
    registerBuiltinInsertProviders(r, spyHandlers())
    expect(r.keys()).toEqual(['blocks', 'patterns', 'templates', 'assets'])
    expect(r.keys()).toEqual([...INSERT_CATEGORY_ORDER])
    expect(r.get('patterns')?.label).toBe('Patterns')
    for (const key of r.keys()) expect(isKnownInsertCategory(key)).toBe(true)
  })
})

describe('useInsertPanelStore', () => {
  it('opens, lists categories, routes to the selected provider, and closes', () => {
    const handlers = spyHandlers()
    registerBuiltinInsertProviders(insertProviderRegistry, handlers)
    const store = useInsertPanelStore()

    expect(store.isOpen).toBe(false)
    store.open()
    expect(store.isOpen).toBe(true)
    expect(store.providers.map((p) => p.key)).toEqual(['blocks', 'patterns', 'templates', 'assets'])

    expect(store.select('templates')).toBe(true)
    expect(handlers.templates).toHaveBeenCalledTimes(1)
    expect(handlers.blocks).not.toHaveBeenCalled() // only the selected experience opens
    expect(store.activeKey).toBe('templates')
    expect(store.lastOpenedKey).toBe('templates')
    expect(store.isOpen).toBe(false) // routing dismisses the launcher
  })

  it('select() is a safe no-op for an unknown / unwired category', () => {
    registerBuiltinInsertProviders(insertProviderRegistry, spyHandlers())
    const store = useInsertPanelStore()
    store.open()
    expect(store.select('marketplace')).toBe(false)
    expect(store.activeKey).toBeNull()
  })

  it('toggles open/closed and reports empty when nothing is wired', () => {
    const store = useInsertPanelStore()
    store.toggle()
    expect(store.isOpen).toBe(true)
    expect(store.isEmpty).toBe(true) // registry not wired
    store.toggle()
    expect(store.isOpen).toBe(false)
  })
})

describe('defaultInsertOpenHandlers', () => {
  it('routes patterns to the existing Pattern Picker', () => {
    const handlers = defaultInsertOpenHandlers()
    expect(usePatternPickerStore().open).toBe(false)
    handlers.patterns()
    expect(usePatternPickerStore().open).toBe(true) // opened the existing picker, no insertion
  })
})

describe('InsertPanel.vue', () => {
  it('renders a button per category and routes on click', async () => {
    const handlers = spyHandlers()
    registerBuiltinInsertProviders(insertProviderRegistry, handlers)
    const store = useInsertPanelStore()
    store.open()

    const wrapper = mount(InsertPanel)
    await nextTick()
    const buttons = wrapper.findAll('.pb-insert__cat')
    expect(buttons).toHaveLength(4)

    await buttons[1].trigger('click') // patterns (deterministic order)
    expect(handlers.patterns).toHaveBeenCalledTimes(1)
    await nextTick()
    expect(wrapper.find('.pb-insert').exists()).toBe(false) // closed after routing
  })

  it('shows nothing when closed', () => {
    const wrapper = mount(InsertPanel)
    expect(wrapper.find('.pb-insert').exists()).toBe(false)
  })
})
