// Phase 26.3.18 — Builder UX guidance: strings, capability badges, tooltips,
// context awareness, smart suggestion, and the non-blocking toast.

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import BuilderElementsPanel from '../BuilderElementsPanel.vue'
import { useEditorStore } from '../../../../editor/stores/editor'
import { usePatternStore } from '../../../../editor/patterns/patternStore'
import { useBuilderToastStore } from '../../builderToastStore'
import {
  capabilityOf,
  tooltipFor,
  elementTwinType,
  PANEL_GUIDANCE,
  ELEMENT_CONTEXT_HELP,
} from '../../builderGuidance'

function seedMetadata() {
  useEditorStore().metadata = {
    version: '1',
    sections: [
      { type: 'image-section', label: 'Image Section', icon: 'heroicon-o-photo', category: 'media' },
      { type: 'text-section', label: 'Text Section', icon: 'heroicon-o-bars-3', category: 'content' },
    ],
    blocks: [
      { type: 'image', label: 'Image', icon: 'heroicon-o-photo', category: 'media' },
      { type: 'text', label: 'Text', icon: 'heroicon-o-bars-3', category: 'content' },
      { type: 'container', label: 'Container', icon: 'heroicon-o-square-3-stack-3d', category: 'layout' },
      { type: 'column', label: 'Column', icon: 'heroicon-o-view-columns', category: 'layout' },
    ],
    tokens: {},
    breakpoints: {},
  } as never
}

function mountPanel() {
  usePatternStore().setMode('components')
  return mount(BuilderElementsPanel)
}

describe('builderGuidance helpers', () => {
  it('assigns capability badges by role', () => {
    expect(capabilityOf('hero', 'section')).toBe('ROOT ONLY')
    expect(capabilityOf('image', 'block')).toBe('COLUMN READY')
    expect(capabilityOf('container', 'block')).toBe('LAYOUT')
    expect(capabilityOf('column', 'block')).toBe('CHILD LAYOUT')
  })

  it('gives explicit tooltips and a safe fallback', () => {
    expect(tooltipFor({ type: 'image', label: 'Image' } as never, 'block')).toMatch(/inside the selected/i)
    expect(tooltipFor({ type: 'image-section', label: 'Image Section' } as never, 'section')).toMatch(/page level/i)
    // Unknown type still yields a sentence, never empty.
    expect(tooltipFor({ type: 'mystery', label: 'Mystery' } as never, 'block')).toContain('Mystery')
  })

  it('maps section→element twins', () => {
    expect(elementTwinType('image-section')).toBe('image')
    expect(elementTwinType('text-section')).toBe('text')
    expect(elementTwinType('hero')).toBeNull()
  })
})

describe('BuilderElementsPanel guidance', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('renders the Sections and Elements panel descriptions', () => {
    seedMetadata()
    const html = mountPanel().html()
    expect(html).toContain(PANEL_GUIDANCE.sections)
    expect(html).toContain(PANEL_GUIDANCE.elements)
  })

  it('shows a ROOT ONLY badge on sections and COLUMN READY / LAYOUT / CHILD LAYOUT on blocks', () => {
    seedMetadata()
    const badges = mountPanel().findAll('.pb-live-builder-element__badge').map((n) => n.text())
    expect(badges).toContain('ROOT ONLY')
    expect(badges).toContain('COLUMN READY')
    expect(badges).toContain('LAYOUT')
    expect(badges).toContain('CHILD LAYOUT')
  })

  it('exposes an intent tooltip + accessible label on every card (no hover-only info)', () => {
    seedMetadata()
    const card = mountPanel().find('.pb-live-builder-element')
    expect(card.attributes('title')).toBeTruthy()
    expect(card.attributes('aria-label')).toContain('.') // "<label>. <tooltip>"
  })

  it('disables element cards and shows the helper when no layout host is selected', () => {
    seedMetadata()
    const store = useEditorStore()
    vi.spyOn(store, 'blockInsertParent').mockReturnValue(null)
    const wrapper = mountPanel()

    expect(wrapper.html()).toContain(ELEMENT_CONTEXT_HELP)
    // Content blocks (image/text) carry the disabled class.
    expect(wrapper.findAll('.pb-live-builder-element.is-disabled').length).toBeGreaterThan(0)
    // Clicking a disabled element card must not insert.
    const spy = vi.spyOn(store, 'addBlockFromPalette')
    wrapper.find('.pb-live-builder-element.is-disabled').trigger('click')
    expect(spy).not.toHaveBeenCalled()
  })

  it('offers the element-twin smart suggestion only when a layout host is selected', async () => {
    seedMetadata()
    const store = useEditorStore()
    const spy = vi.spyOn(store, 'addBlockFromPalette').mockResolvedValue(true)

    // No host → no suggestion.
    vi.spyOn(store, 'blockInsertParent').mockReturnValue(null)
    expect(mountPanel().find('.pb-live-builder-suggest').exists()).toBe(false)

    // Host selected → suggestion appears; clicking reuses addBlockFromPalette.
    vi.mocked(store.blockInsertParent).mockReturnValue('col-1')
    const wrapper = mountPanel()
    const suggest = wrapper.find('.pb-live-builder-suggest')
    expect(suggest.exists()).toBe(true)
    await suggest.find('.pb-live-builder-suggest__action').trigger('click')
    expect(spy).toHaveBeenCalled()
    expect((spy.mock.calls[0][0] as { type: string }).type).toMatch(/^(image|text)$/)
  })
})

describe('builderToastStore', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('shows, exposes, and dismisses a toast', () => {
    const toasts = useBuilderToastStore()
    const id = toasts.notifySectionAtPageLevel('Sections go at page level')
    expect(toasts.toasts).toHaveLength(1)
    expect(toasts.toasts[0].message).toContain('page level')
    toasts.dismiss(id)
    expect(toasts.toasts).toHaveLength(0)
  })

  it('carries an optional action', () => {
    const toasts = useBuilderToastStore()
    const run = vi.fn()
    toasts.show('msg', { action: { label: 'Do it', run }, ttl: 0 })
    toasts.toasts[0].action?.run()
    expect(run).toHaveBeenCalled()
  })
})
