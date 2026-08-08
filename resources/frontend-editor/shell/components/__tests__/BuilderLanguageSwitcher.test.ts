import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia, type Pinia } from 'pinia'
import BuilderLanguageSwitcher from '../BuilderLanguageSwitcher.vue'
import { useEditorStore } from '../../../../editor/stores/editor'

const EN = { locale: 'en', label: 'English', is_source: true, is_current: true, has_translation: true, has_layout: true, editor_url: '/pb/page/1/frontend?locale=en' }
const VI = { locale: 'vi', label: 'Tiếng Việt', is_source: false, is_current: false, has_translation: true, has_layout: true, editor_url: '/pb/page/1/frontend?locale=vi' }

function scene(opts: { dirty?: boolean; locales?: unknown[]; locale?: string } = {}) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const editor = useEditorStore()
  editor.$patch({
    boot: {
      locale: opts.locale ?? 'en',
      context: { translation: { available_locales: opts.locales ?? [EN, VI] } },
    } as never,
    dirty: opts.dirty ?? false,
  })
  return { pinia, editor }
}

async function openMenuAndPickVi(wrapper: VueWrapper): Promise<void> {
  await wrapper.get('[aria-label="Editing language"]').trigger('click')
  const vi = wrapper.findAll('[role="option"]').find((b) => b.text().includes('Tiếng Việt'))
  await vi!.trigger('click')
}

function dialogButton(wrapper: VueWrapper, label: string) {
  return wrapper.findAll('.pb-locale-dialog__actions button').find((b) => b.text().includes(label))
}

let assignSpy: ReturnType<typeof vi.spyOn>
beforeEach(() => {
  assignSpy = vi.spyOn(window.location, 'assign').mockImplementation(() => {})
})
afterEach(() => {
  assignSpy.mockRestore()
})

describe('BuilderLanguageSwitcher', () => {
  it('hides when there is only one locale', () => {
    const { pinia } = scene({ locales: [EN] })
    const wrapper = mount(BuilderLanguageSwitcher, { global: { plugins: [pinia] } })
    expect(wrapper.find('.pb-live-builder-langswitch').exists()).toBe(false)
  })

  it('shows the current locale label and lists the available locales', async () => {
    const { pinia } = scene()
    const wrapper = mount(BuilderLanguageSwitcher, { global: { plugins: [pinia] } })
    expect(wrapper.get('[aria-label="Editing language"]').text()).toContain('English')
    await wrapper.get('[aria-label="Editing language"]').trigger('click')
    const options = wrapper.findAll('[role="option"]')
    expect(options).toHaveLength(2)
    expect(options[1].text()).toContain('Tiếng Việt')
  })

  it('labels the editing language and, on the source locale, names it as the source', () => {
    const { pinia } = scene({ locale: 'en' })
    const wrapper = mount(BuilderLanguageSwitcher, { global: { plugins: [pinia] } })
    expect(wrapper.get('.pb-live-builder-langswitch__heading').text()).toBe('Editing language')
    const source = wrapper.get('.pb-live-builder-langswitch__source').text()
    expect(source).toContain('Source language: English')
  })

  it('names the source it is translating from when editing a translation', () => {
    const { pinia } = scene({
      locale: 'vi',
      locales: [{ ...EN, is_current: false }, { ...VI, is_current: true }],
    })
    const wrapper = mount(BuilderLanguageSwitcher, { global: { plugins: [pinia] } })
    expect(wrapper.get('[aria-label="Editing language"]').text()).toContain('Tiếng Việt')
    expect(wrapper.get('.pb-live-builder-langswitch__source').text()).toContain('Translating from: English')
  })

  it('navigates immediately to the server URL when the editor is clean', async () => {
    const { pinia } = scene({ dirty: false })
    const wrapper = mount(BuilderLanguageSwitcher, { global: { plugins: [pinia] } })
    await openMenuAndPickVi(wrapper)
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(assignSpy).toHaveBeenCalledWith(VI.editor_url)
  })

  it('opens the unsaved-change dialog (no navigation) when dirty', async () => {
    const { pinia } = scene({ dirty: true })
    const wrapper = mount(BuilderLanguageSwitcher, { global: { plugins: [pinia] } })
    await openMenuAndPickVi(wrapper)
    expect(wrapper.get('[role="dialog"]')).toBeTruthy()
    expect(assignSpy).not.toHaveBeenCalled()
  })

  it('Stay keeps the current locale and closes the dialog', async () => {
    const { pinia } = scene({ dirty: true })
    const wrapper = mount(BuilderLanguageSwitcher, { global: { plugins: [pinia] } })
    await openMenuAndPickVi(wrapper)
    await dialogButton(wrapper, 'Stay and continue editing')!.trigger('click')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(assignSpy).not.toHaveBeenCalled()
  })

  it('Switch without saving navigates without calling save', async () => {
    const { pinia, editor } = scene({ dirty: true })
    const saveSpy = vi.spyOn(editor, 'saveDraft')
    const wrapper = mount(BuilderLanguageSwitcher, { global: { plugins: [pinia] } })
    await openMenuAndPickVi(wrapper)
    await dialogButton(wrapper, 'Switch without saving')!.trigger('click')
    expect(saveSpy).not.toHaveBeenCalled()
    expect(assignSpy).toHaveBeenCalledWith(VI.editor_url)
  })

  it('Save and switch saves once, then navigates on success', async () => {
    const { pinia, editor } = scene({ dirty: true })
    const saveSpy = vi.spyOn(editor, 'saveDraft').mockImplementation(async () => {
      editor.dirty = false
    })
    const wrapper = mount(BuilderLanguageSwitcher, { global: { plugins: [pinia] } })
    await openMenuAndPickVi(wrapper)
    await dialogButton(wrapper, 'Save and switch')!.trigger('click')
    await flushPromises()
    expect(saveSpy).toHaveBeenCalledOnce()
    expect(assignSpy).toHaveBeenCalledWith(VI.editor_url)
  })

  it('Save and switch does NOT navigate when the save fails (stays dirty)', async () => {
    const { pinia, editor } = scene({ dirty: true })
    vi.spyOn(editor, 'saveDraft').mockImplementation(async () => {
      /* save aborts — dirty stays true */
    })
    const wrapper = mount(BuilderLanguageSwitcher, { global: { plugins: [pinia] } })
    await openMenuAndPickVi(wrapper)
    await dialogButton(wrapper, 'Save and switch')!.trigger('click')
    await flushPromises()
    expect(assignSpy).not.toHaveBeenCalled()
    expect(wrapper.get('[role="dialog"]')).toBeTruthy()
  })
})
