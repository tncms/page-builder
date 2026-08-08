// Phase 26.1 — LayoutPresetPopup renders the metadata presets, dispatches the
// apply, and enforces the content-loss confirm for existing containers.
import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import LayoutPresetPopup from '../LayoutPresetPopup.vue'
import { useToolbarStore } from '../../../../toolbar/ToolbarManager'
import { useEditorStore } from '../../../../../editor/stores/editor'
import type { ColumnLayoutPreset, LayoutPayload } from '../../../../../editor/types'

const PRESETS: ColumnLayoutPreset[] = Array.from({ length: 15 }, (_, i) => ({
  id: `preset-${i}`,
  label: `L${i}`,
  widths: i === 0 ? ['30', '70'] : ['50', '50'],
}))

// A container 'c1' holding two columns; col2 has a child block (content).
function payload(): LayoutPayload {
  return {
    schema: 'pagebuilder/01',
    sections: [
      {
        id: 's1',
        type: 'section',
        data: {},
        blocks: [
          {
            id: 'c1',
            type: 'container',
            data: {
              blocks: [
                { id: 'col1', type: 'column', data: { width: '50', blocks: [] } },
                {
                  id: 'col2',
                  type: 'column',
                  data: { width: '50', blocks: [{ id: 't1', type: 'text', data: {} }] },
                },
              ],
            },
          },
        ],
      },
    ],
  } as unknown as LayoutPayload
}

function seed(selected: string) {
  const editor = useEditorStore()
  editor.currentPayload = payload()
  editor.metadata = { columnLayouts: PRESETS } as never
  editor.selectComponent(selected)
  const toolbar = useToolbarStore()
  toolbar.layoutPopupOpen = true
  return { editor, toolbar }
}

describe('LayoutPresetPopup (Phase 26.1)', () => {
  beforeEach(() => setActivePinia(createPinia()))
  afterEach(() => vi.restoreAllMocks())

  it('renders all 15 presets with proportion bars', () => {
    seed('c1')
    const wrapper = mount(LayoutPresetPopup)
    const buttons = wrapper.findAll('.pb-live-toolbar__layout-card')
    expect(buttons.length).toBe(15)
    // First preset has two segments (30 / 70).
    expect(wrapper.find('[data-preset-id="preset-0"]').findAll('.pb-live-toolbar__layout-bar').length).toBe(2)
  })

  it('dispatches applyLayoutPreset with the chosen widths (container, no loss)', async () => {
    const { toolbar } = seed('c1')
    const apply = vi.spyOn(toolbar, 'applyLayoutPreset').mockResolvedValue()
    // preset-1 keeps 2 columns → no content loss, no confirm.
    const wrapper = mount(LayoutPresetPopup)
    await wrapper.find('[data-preset-id="preset-1"]').trigger('click')
    expect(apply).toHaveBeenCalledWith(['50', '50'])
  })

  it('blocks dispatch when the content-loss confirm is declined', async () => {
    const { editor, toolbar } = seed('c1')
    // Force actionKind = container.
    expect(toolbar.actionKind).toBe('container')
    const apply = vi.spyOn(toolbar, 'applyLayoutPreset').mockResolvedValue()
    // Single-column preset drops col2 (which holds content) → confirm shown.
    const single: ColumnLayoutPreset = { id: 'single', label: '100', widths: ['100'] }
    editor.metadata = { columnLayouts: [single] } as never
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(false)
    const wrapper = mount(LayoutPresetPopup)
    await wrapper.find('[data-preset-id="single"]').trigger('click')
    expect(confirmSpy).toHaveBeenCalledTimes(1)
    expect(apply).not.toHaveBeenCalled()
  })

  it('proceeds when the content-loss confirm is accepted', async () => {
    const { editor, toolbar } = seed('c1')
    const apply = vi.spyOn(toolbar, 'applyLayoutPreset').mockResolvedValue()
    const single: ColumnLayoutPreset = { id: 'single', label: '100', widths: ['100'] }
    editor.metadata = { columnLayouts: [single] } as never
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true)
    const wrapper = mount(LayoutPresetPopup)
    await wrapper.find('[data-preset-id="single"]').trigger('click')
    expect(confirmSpy).toHaveBeenCalledTimes(1)
    expect(apply).toHaveBeenCalledWith(['100'])
  })
})

// Phase 26.1.1 — production UX completion.
describe('LayoutPresetPopup (Phase 26.1.1)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    globalThis.localStorage?.clear?.()
  })
  afterEach(() => vi.restoreAllMocks())

  it('is a listbox of options with the a11y roles', () => {
    seed('c1')
    const wrapper = mount(LayoutPresetPopup)
    expect(wrapper.find('[role="listbox"]').exists()).toBe(true)
    expect(wrapper.findAll('[role="option"]').length).toBe(15)
    // Search input is labelled.
    expect(wrapper.find('input[type="search"]').attributes('aria-label')).toBe('Search layouts')
  })

  it('is 100% metadata-driven: renders exactly the presets metadata provides', () => {
    const editor = useEditorStore()
    const toolbar = useToolbarStore()
    editor.currentPayload = payload()
    // An injected fake preset from a hypothetical external provider.
    const fake: ColumnLayoutPreset = {
      id: 'x-plugin-preset',
      label: 'Plugin Layout',
      widths: ['40', '60'],
      category: 'common',
      description: 'From a plugin',
      keywords: ['plugin', 'custom'],
    }
    editor.metadata = { columnLayouts: [fake] } as never
    editor.selectComponent('c1')
    toolbar.layoutPopupOpen = true
    const wrapper = mount(LayoutPresetPopup)
    const cards = wrapper.findAll('[role="option"]')
    expect(cards.length).toBe(1)
    expect(wrapper.find('[data-preset-id="x-plugin-preset"]').exists()).toBe(true)
  })

  it('does not render duplicate cards for duplicate ids (provider-shape safety)', () => {
    const editor = useEditorStore()
    const toolbar = useToolbarStore()
    editor.currentPayload = payload()
    const dup: ColumnLayoutPreset = { id: 'dupe', label: 'Dupe', widths: ['50', '50'] }
    editor.metadata = { columnLayouts: [dup, { ...dup }] } as never
    editor.selectComponent('c1')
    toolbar.layoutPopupOpen = true
    const wrapper = mount(LayoutPresetPopup)
    // Two entries share the same :key, so Vue renders one card.
    expect(wrapper.findAll('[data-preset-id="dupe"]').length).toBe(1)
  })

  it('filters the grid by the search query (label/description/keywords/category)', async () => {
    const editor = useEditorStore()
    const toolbar = useToolbarStore()
    editor.currentPayload = payload()
    editor.metadata = {
      columnLayouts: [
        { id: 'a', label: 'Halves', widths: ['50', '50'], keywords: ['equal'] },
        { id: 'b', label: 'Sidebar', widths: ['30', '70'], keywords: ['sidebar'] },
      ],
    } as never
    editor.selectComponent('c1')
    toolbar.layoutPopupOpen = true
    const wrapper = mount(LayoutPresetPopup)
    await wrapper.find('input[type="search"]').setValue('sidebar')
    const cards = wrapper.findAll('[role="option"]')
    expect(cards.length).toBe(1)
    expect(wrapper.find('[data-preset-id="b"]').exists()).toBe(true)
  })

  it('badges the current layout with aria-current and a Current marker', () => {
    // Container c1 columns are 50/50 → matches a 50/50 preset.
    const editor = useEditorStore()
    const toolbar = useToolbarStore()
    editor.currentPayload = payload()
    editor.metadata = {
      columnLayouts: [
        { id: 'sidebar', label: 'Sidebar', widths: ['30', '70'] },
        { id: 'halves', label: 'Halves', widths: ['50', '50'] },
      ],
    } as never
    editor.selectComponent('c1')
    toolbar.layoutPopupOpen = true
    const wrapper = mount(LayoutPresetPopup)
    const current = wrapper.find('[data-preset-id="halves"]')
    expect(current.attributes('aria-current')).toBe('true')
    expect(current.text()).toContain('Current')
    // The non-matching one is not marked.
    expect(wrapper.find('[data-preset-id="sidebar"]').attributes('aria-current')).toBeUndefined()
  })

  it('shows the Custom Layout hint when no preset matches', () => {
    const editor = useEditorStore()
    const toolbar = useToolbarStore()
    editor.currentPayload = payload() // 50/50
    editor.metadata = { columnLayouts: [{ id: 'sidebar', label: 'Sidebar', widths: ['30', '70'] }] } as never
    editor.selectComponent('c1')
    toolbar.layoutPopupOpen = true
    const wrapper = mount(LayoutPresetPopup)
    expect(wrapper.find('.pb-live-toolbar__layout-hint').text()).toContain('Custom Layout')
  })

  it('toggles a favorite via the star button and persists it', async () => {
    seed('c1')
    const wrapper = mount(LayoutPresetPopup)
    const star = wrapper.find('[data-preset-id="preset-1"] .pb-live-toolbar__layout-star')
    expect(star.attributes('aria-pressed')).toBe('false')
    await star.trigger('click')
    expect(star.attributes('aria-pressed')).toBe('true')
    // Persisted, and a Favorites tab appears.
    expect(JSON.parse(globalThis.localStorage.getItem('pb:layout-presets') as string).favorites).toContain(
      'preset-1',
    )
  })

  it('clicking the star does not apply the preset (stops propagation)', async () => {
    const { toolbar } = seed('c1')
    const apply = vi.spyOn(toolbar, 'applyLayoutPreset').mockResolvedValue()
    const wrapper = mount(LayoutPresetPopup)
    await wrapper.find('[data-preset-id="preset-1"] .pb-live-toolbar__layout-star').trigger('click')
    expect(apply).not.toHaveBeenCalled()
  })

  it('records the applied preset as recent', async () => {
    const { toolbar } = seed('c1')
    vi.spyOn(toolbar, 'applyLayoutPreset').mockResolvedValue()
    const wrapper = mount(LayoutPresetPopup)
    await wrapper.find('[data-preset-id="preset-1"]').trigger('click')
    expect(JSON.parse(globalThis.localStorage.getItem('pb:layout-presets') as string).recent).toContain(
      'preset-1',
    )
  })

  it('Enter on a focused card applies its preset (keyboard nav)', async () => {
    const { toolbar } = seed('c1')
    const apply = vi.spyOn(toolbar, 'applyLayoutPreset').mockResolvedValue()
    const wrapper = mount(LayoutPresetPopup)
    // First card is focused (roving tabindex 0); Enter on the grid applies it.
    await wrapper.find('[role="listbox"]').trigger('keydown', { key: 'Enter' })
    // preset-0 has widths 30/70.
    expect(apply).toHaveBeenCalledWith(['30', '70'])
  })

  it('ArrowRight moves roving focus to the next card', async () => {
    seed('c1')
    const wrapper = mount(LayoutPresetPopup)
    const cards = () => wrapper.findAll('[role="option"]')
    expect(cards()[0].attributes('tabindex')).toBe('0')
    await wrapper.find('[role="listbox"]').trigger('keydown', { key: 'ArrowRight' })
    await wrapper.vm.$nextTick()
    expect(cards()[0].attributes('tabindex')).toBe('-1')
    expect(cards()[1].attributes('tabindex')).toBe('0')
  })
})
