import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import BuilderElementsPanel from '../BuilderElementsPanel.vue'
import { useEditorStore } from '../../../../editor/stores/editor'
import { usePatternStore } from '../../../../editor/patterns/patternStore'

// Phase 25: Patterns is the default view — these tests exercise the raw
// Components palette, so switch to it first.
function mountComponents() {
  usePatternStore().setMode('components')
  return mount(BuilderElementsPanel)
}

function seedMetadata() {
  const store = useEditorStore()
  store.metadata = {
    version: '1',
    sections: [
      { type: 'hero', label: 'Hero', icon: 'heroicon-o-sparkles', category: 'layout' },
    ],
    blocks: [
      { type: 'heading', label: 'Heading', icon: 'heroicon-o-h1', category: 'content' },
      { type: 'image', label: 'Image', icon: 'heroicon-o-photo', category: 'media' },
      { type: 'button', label: 'Button', icon: 'heroicon-o-cursor-arrow-rays', category: 'conversion' },
    ],
    tokens: {},
    breakpoints: {},
  } as never
}

describe('BuilderElementsPanel (Phase 23)', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('renders real SVG icons, never the raw heroicon string', () => {
    seedMetadata()
    const wrapper = mountComponents()

    expect(wrapper.findAll('svg').length).toBeGreaterThan(0)
    expect(wrapper.html()).not.toContain('heroicon-o-')
  })

  it('groups blocks under category headings', () => {
    seedMetadata()
    const wrapper = mountComponents()

    const categories = wrapper.findAll('.pb-live-builder-panel__category').map((n) => n.text())
    expect(categories).toContain('Content')
    expect(categories).toContain('Media')
    expect(categories).toContain('Conversion')
  })

  it('shows a category chip on section items', () => {
    seedMetadata()
    const wrapper = mountComponents()
    expect(wrapper.find('.pb-live-builder-element__category').text()).toBe('Layout')
  })

  // Phase 26.3.15 — every card declares its kind, so "Image Section" (section)
  // is never confused with "Image" (element).
  it('badges section cards SECTION and element cards ELEMENT', () => {
    seedMetadata()
    const wrapper = mountComponents()

    const sectionKinds = wrapper.findAll('.pb-live-builder-element__kind--section')
    const elementKinds = wrapper.findAll('.pb-live-builder-element__kind--element')

    // One section (hero) and three blocks (heading/image/button) in the fixture.
    expect(sectionKinds).toHaveLength(1)
    expect(elementKinds).toHaveLength(3)
    expect(sectionKinds[0].text()).toBe('Section')
    expect(elementKinds[0].text()).toBe('Element')
  })

  // PB-XREG-4 — theme-origin definitions carry a server-derived origin; built-ins/plugins stay unbadged.
  it('marks only theme-origin cards with a Theme origin chip in the visible and accessible name', () => {
    seedMetadata()
    const store = useEditorStore()
    store.metadata!.sections.push({
      type: 'aurora:hero', label: 'Aurora hero', category: 'theme', origin: { kind: 'theme', slug: 'aurora' },
    } as never)
    const wrapper = mountComponents()

    const chips = wrapper.findAll('.pb-live-builder-element__origin')
    expect(chips).toHaveLength(1)
    expect(chips[0].text()).toBe('Theme')
    const card = wrapper.findAll('.pb-live-builder-element').find((b) => b.text().includes('Aurora hero'))!
    expect(card.attributes('aria-label')).toContain('Provided by the active theme')
    const hero = wrapper.findAll('.pb-live-builder-element').find((b) => b.text().startsWith('Hero'))!
    expect(hero.attributes('aria-label')).not.toContain('theme')
    expect(wrapper.html()).not.toContain('aurora::')
  })
})
