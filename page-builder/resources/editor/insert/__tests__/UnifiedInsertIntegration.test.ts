// Phase 26.38 — Unified Insert Builder integration. The router is wired to the real Builder
// surfaces (blocks → palette, patterns → Pattern Picker, templates → Template Browser, assets →
// Asset Picker) via host handlers, reachable from a toolbar button and a Ctrl/Cmd+I shortcut.
// Opening / routing NEVER inserts, resolves, dispatches a command, or touches BuilderState.

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import {
  editorInsertOpenHandlers,
  registerBuilderInsertProviders,
  handleInsertShortcut,
  useInsertPanelStore,
  useInsertSurfacesStore,
  insertProviderRegistry,
  INSERT_CATEGORY_ORDER,
  InsertPanel,
} from '..'
import { usePatternPickerStore } from '../../patterns/picker/patternPickerStore'
import { useAssetPickerStore } from '../../assets'
import EditorTopBar from '../../components/EditorTopBar.vue'
import InsertSurfaces from '../InsertSurfaces.vue'

beforeEach(() => {
  setActivePinia(createPinia())
  insertProviderRegistry.clear()
})
afterEach(() => insertProviderRegistry.clear())

/** Build a minimal keyboard event with a preventDefault spy. */
function keyEvent(init: Partial<KeyboardEvent> & { key: string }): KeyboardEvent {
  return {
    ctrlKey: false,
    metaKey: false,
    altKey: false,
    shiftKey: false,
    target: null,
    preventDefault: vi.fn(),
    ...init,
  } as unknown as KeyboardEvent
}

describe('editorInsertOpenHandlers (host wiring)', () => {
  it('routes each category to its real surface and inserts nothing', () => {
    const handlers = editorInsertOpenHandlers()
    const surfaces = useInsertSurfacesStore()
    const patternPicker = usePatternPickerStore()
    const assetPicker = useAssetPickerStore()

    // blocks → focus the component palette (a token bump; no insertion).
    const before = surfaces.paletteToken
    handlers.blocks()
    expect(surfaces.paletteToken).toBe(before + 1)

    // patterns → open the existing Pattern Picker.
    expect(patternPicker.open).toBe(false)
    handlers.patterns()
    expect(patternPicker.open).toBe(true)

    // templates → open the Template Browser overlay.
    expect(surfaces.templatesOpen).toBe(false)
    handlers.templates()
    expect(surfaces.templatesOpen).toBe(true)

    // assets → open the existing Asset Picker.
    expect(assetPicker.open).toBe(false)
    void handlers.assets()
    expect(assetPicker.open).toBe(true)
  })
})

describe('registerBuilderInsertProviders', () => {
  it('registers the four categories in deterministic order', () => {
    registerBuilderInsertProviders()
    expect(insertProviderRegistry.keys()).toEqual([...INSERT_CATEGORY_ORDER])
  })

  it('is idempotent — re-registering never trips the duplicate guard', () => {
    registerBuilderInsertProviders()
    expect(() => registerBuilderInsertProviders()).not.toThrow()
    expect(insertProviderRegistry.keys()).toEqual([...INSERT_CATEGORY_ORDER])
  })
})

describe('panel routing (via the wired providers)', () => {
  beforeEach(() => registerBuilderInsertProviders())

  it('select() routes to only the chosen surface, flipping UI state only', () => {
    const panel = useInsertPanelStore()
    const surfaces = useInsertSurfacesStore()
    const patternPicker = usePatternPickerStore()

    panel.open()
    expect(panel.select('templates')).toBe(true)
    expect(surfaces.templatesOpen).toBe(true)
    expect(patternPicker.open).toBe(false) // no other experience opened
    expect(panel.isOpen).toBe(false) // routing dismisses the launcher
    expect(panel.lastOpenedKey).toBe('templates')
  })
})

describe('handleInsertShortcut (Ctrl/Cmd+I)', () => {
  it('opens on a bare Ctrl+I or Cmd+I', () => {
    const openCtrl = vi.fn()
    expect(handleInsertShortcut(keyEvent({ key: 'i', ctrlKey: true }), openCtrl)).toBe(true)
    expect(openCtrl).toHaveBeenCalledTimes(1)

    const openMeta = vi.fn()
    expect(handleInsertShortcut(keyEvent({ key: 'I', metaKey: true }), openMeta)).toBe(true)
    expect(openMeta).toHaveBeenCalledTimes(1)
  })

  it('ignores the key without a modifier, or with shift/alt', () => {
    const open = vi.fn()
    expect(handleInsertShortcut(keyEvent({ key: 'i' }), open)).toBe(false)
    expect(handleInsertShortcut(keyEvent({ key: 'i', ctrlKey: true, shiftKey: true }), open)).toBe(false)
    expect(handleInsertShortcut(keyEvent({ key: 'i', ctrlKey: true, altKey: true }), open)).toBe(false)
    expect(open).not.toHaveBeenCalled()
  })

  it('respects editor focus — never fires while typing in a field', () => {
    const open = vi.fn()
    const target = { tagName: 'INPUT', isContentEditable: false }
    expect(handleInsertShortcut(keyEvent({ key: 'i', ctrlKey: true, target } as never), open)).toBe(false)
    expect(open).not.toHaveBeenCalled()
  })
})

describe('InsertPanel.vue — Builder UX', () => {
  beforeEach(() => registerBuilderInsertProviders())

  it('closes on Escape without routing anywhere', async () => {
    const store = useInsertPanelStore()
    const surfaces = useInsertSurfacesStore()
    store.open()
    const wrapper = mount(InsertPanel, { attachTo: document.body })
    await nextTick()

    await wrapper.find('.pb-insert').trigger('keydown.esc')
    expect(store.isOpen).toBe(false)
    expect(surfaces.templatesOpen).toBe(false) // escape never inserts / routes
    wrapper.unmount()
  })

  it('restores focus to the opener when it closes', async () => {
    const opener = document.createElement('button')
    document.body.appendChild(opener)
    opener.focus()
    expect(document.activeElement).toBe(opener)

    const store = useInsertPanelStore()
    const wrapper = mount(InsertPanel, { attachTo: document.body })
    store.open()
    await nextTick()
    await nextTick()

    store.close()
    await nextTick()
    expect(document.activeElement).toBe(opener)

    wrapper.unmount()
    opener.remove()
  })

  it('exposes the categories as an accessible menu of menuitems', async () => {
    useInsertPanelStore().open()
    const wrapper = mount(InsertPanel)
    await nextTick()
    expect(wrapper.find('[role="dialog"]').attributes('aria-modal')).toBe('true')
    expect(wrapper.find('[role="menu"]').exists()).toBe(true)
    expect(wrapper.findAll('[role="menuitem"]')).toHaveLength(4)
  })
})

describe('EditorTopBar — Insert launcher entry', () => {
  it('renders a single "+ Insert" button that opens the launcher', async () => {
    const panel = useInsertPanelStore()
    const wrapper = mount(EditorTopBar)

    const buttons = wrapper.findAll('.pb-editor-btn--insert')
    expect(buttons).toHaveLength(1) // no duplicate launcher
    expect(buttons[0].text()).toContain('Insert')
    expect(panel.isOpen).toBe(false)

    await buttons[0].trigger('click')
    expect(panel.isOpen).toBe(true)
  })
})

describe('InsertSurfaces.vue — host overlays for routed pickers', () => {
  it('shows the Pattern Picker overlay while the picker is open and dismisses it', async () => {
    const patternPicker = usePatternPickerStore()
    const wrapper = mount(InsertSurfaces, { attachTo: document.body })
    expect(wrapper.find('[aria-label="Insert a pattern"]').exists()).toBe(false)

    patternPicker.openPicker()
    await nextTick()
    expect(wrapper.find('[aria-label="Insert a pattern"]').exists()).toBe(true)

    await wrapper.find('.pb-insert-surface__close').trigger('click')
    expect(patternPicker.open).toBe(false)
    wrapper.unmount()
  })

  it('shows the Template Browser overlay while templates are open', async () => {
    const surfaces = useInsertSurfacesStore()
    const wrapper = mount(InsertSurfaces, { attachTo: document.body })
    expect(wrapper.find('[aria-label="Insert a template"]').exists()).toBe(false)

    surfaces.openTemplates()
    await nextTick()
    expect(wrapper.find('[aria-label="Insert a template"]').exists()).toBe(true)

    await wrapper.find('.pb-insert-surface__close').trigger('click')
    expect(surfaces.templatesOpen).toBe(false)
    wrapper.unmount()
  })
})
