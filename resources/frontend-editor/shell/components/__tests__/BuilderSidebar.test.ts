import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia, type Pinia } from 'pinia'
import BuilderSidebar from '../BuilderSidebar.vue'
import { useEditorStore } from '../../../../editor/stores/editor'

function scene(): Pinia {
  const pinia = createPinia()
  setActivePinia(pinia)
  const editor = useEditorStore()
  editor.currentPayload = {
    schema: 'pagebuilder/01',
    sections: [{ type: 'hero', id: 'h1', data: {}, blocks: [] }],
  }
  return pinia
}

function tabs(wrapper: ReturnType<typeof mount>) {
  return wrapper.findAll('.pb-live-builder-panel-tab')
}

const EN = { locale: 'en', label: 'English', is_source: true, is_current: true, has_translation: true, has_layout: true, editor_url: '/pb/page/1/frontend?locale=en' }
const VI = { locale: 'vi', label: 'Tiếng Việt', is_source: false, is_current: false, has_translation: true, has_layout: true, editor_url: '/pb/page/1/frontend?locale=vi' }

function withLocales(pinia: Pinia, locales: unknown[]): void {
  const editor = useEditorStore()
  editor.$patch({ boot: { locale: 'en', context: { translation: { available_locales: locales } } } as never })
}

describe('BuilderSidebar', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('renders exactly one locale switcher, above the panel tabs, when >1 locale', () => {
    const pinia = scene()
    withLocales(pinia, [EN, VI])
    const wrapper = mount(BuilderSidebar, { global: { plugins: [pinia] } })

    const switchers = wrapper.findAll('.pb-live-builder-langswitch')
    expect(switchers).toHaveLength(1)

    // The switcher precedes the panel tabs in the DOM (rendered above them).
    const tabsNav = wrapper.get('.pb-live-builder-panel-tabs')
    const pos = switchers[0].element.compareDocumentPosition(tabsNav.element)
    expect(pos & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
  })

  it('hides the locale switcher when only one locale exists', () => {
    const pinia = scene()
    withLocales(pinia, [EN])
    const wrapper = mount(BuilderSidebar, { global: { plugins: [pinia] } })
    expect(wrapper.find('.pb-live-builder-langswitch').exists()).toBe(false)
  })

  it('renders the three panel tabs, Elements active by default', () => {
    const wrapper = mount(BuilderSidebar, { global: { plugins: [scene()] } })
    const t = tabs(wrapper)
    expect(t).toHaveLength(3)
    expect(t.map((b) => b.text())).toEqual(['＋Elements', '≣Navigator', '⚙Options'])
    expect(t[0].classes()).toContain('is-active')
  })

  it('switches panels when a tab is clicked', async () => {
    const wrapper = mount(BuilderSidebar, { global: { plugins: [scene()] } })
    await tabs(wrapper)[1].trigger('click')
    expect(tabs(wrapper)[1].classes()).toContain('is-active')
    expect(tabs(wrapper)[0].classes()).not.toContain('is-active')
  })

  it('auto-switches to Options when a component is selected', async () => {
    const pinia = scene()
    const wrapper = mount(BuilderSidebar, { global: { plugins: [pinia] } })
    const editor = useEditorStore()

    editor.selectedComponentId = 'h1'
    await wrapper.vm.$nextTick()
    expect(tabs(wrapper)[2].classes()).toContain('is-active')

    // Deselecting returns to Elements.
    editor.selectedComponentId = null
    await wrapper.vm.$nextTick()
    expect(tabs(wrapper)[0].classes()).toContain('is-active')
  })
})
