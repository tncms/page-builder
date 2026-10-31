import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import BuilderLanguageSwitcher from '../BuilderLanguageSwitcher.vue'
import { useEditorStore } from '../../../../editor/stores/editor'
import { LayoutConflictError } from '../../../../editor/api/client'

// PB-CORE-AUTHORITY-REMEDIATION-R1 — no source content to copy: an explicit decision
// (Create blank translation / Return to content editor / Cancel), never an error.
const CREATE_URL = '/api/page-builder/editor/context/page/1/translations'
const EN = { locale: 'en', label: 'English', is_source: true, is_current: true, has_translation: true, has_layout: true, editor_url: '/pb/page/1/frontend?locale=en', can_create: false, create_url: null }
const VI_NEW = { locale: 'vi', label: 'Tiếng Việt', is_source: false, is_current: false, has_translation: false, has_layout: false, editor_url: null, can_create: true, create_url: CREATE_URL, source_layout: 'absent', requires_blank_decision: true }

const decisionError = () =>
  new LayoutConflictError('The source language has no Page Builder content to copy.', 'pb_blank_layout_decision_required', {
    source_layout: 'absent',
    locale: 'vi',
    choices: ['blank', 'return', 'cancel'],
    return_url: '/admin/pages/1/edit?locale=en',
  })

function mountWith(createTranslation: ReturnType<typeof vi.fn>): VueWrapper {
  const pinia = createPinia()
  setActivePinia(pinia)
  const editor = useEditorStore()
  editor.$patch({
    boot: { locale: 'en', context: { translation: { available_locales: [EN, VI_NEW] } } } as never,
    dirty: false,
  })
  editor.client = { createTranslation } as never

  return mount(BuilderLanguageSwitcher, { global: { plugins: [pinia] }, attachTo: document.body })
}

async function pickVi(wrapper: VueWrapper): Promise<void> {
  await wrapper.get('[aria-label="Editing language"]').trigger('click')
  await wrapper.findAll('[role="option"]').find((b) => b.text().includes('Tiếng Việt'))!.trigger('click')
  await flushPromises()
}

let assignSpy: ReturnType<typeof vi.spyOn>
beforeEach(() => {
  assignSpy = vi.spyOn(window.location, 'assign').mockImplementation(() => {})
})
afterEach(() => {
  assignSpy.mockRestore()
  document.body.innerHTML = ''
})

describe('BuilderLanguageSwitcher — blank-layout decision', () => {
  it('asks explicitly instead of failing when the server returns the decision', async () => {
    const createTranslation = vi.fn(async () => {
      throw decisionError()
    })
    const wrapper = mountWith(createTranslation)

    await pickVi(wrapper)

    const dialog = wrapper.get('[data-test="blank-translation-dialog"]')
    expect(dialog.attributes('role')).toBe('dialog')
    expect(dialog.attributes('aria-modal')).toBe('true')
    expect(dialog.text()).toContain('no Page Builder content to copy')
    expect(dialog.findAll('button').map((b) => b.text())).toEqual([
      'Cancel',
      'Return to content editor',
      'Create blank translation',
    ])
    expect(document.activeElement?.textContent?.trim()).toBe('Cancel')
    expect(wrapper.find('.pb-live-builder-langswitch__error').exists()).toBe(false)
    expect(createTranslation).toHaveBeenCalledTimes(1)
    expect(createTranslation).toHaveBeenCalledWith(CREATE_URL, 'vi')
    expect(assignSpy).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('Create blank translation posts the explicit choice and opens the new editor', async () => {
    const createTranslation = vi
      .fn()
      .mockImplementationOnce(async () => {
        throw decisionError()
      })
      .mockImplementationOnce(async () => ({ locale: 'vi', created: true, layout: 'blank', content_translation_id: 9, editor_url: '/pb/page/1/frontend?locale=vi' }))
    const wrapper = mountWith(createTranslation)

    await pickVi(wrapper)
    await wrapper.get('[data-test="blank-decision-create"]').trigger('click')
    await flushPromises()

    expect(createTranslation).toHaveBeenNthCalledWith(2, CREATE_URL, 'vi', 'blank')
    expect(assignSpy).toHaveBeenCalledWith('/pb/page/1/frontend?locale=vi')
    wrapper.unmount()
  })

  it('Return to content editor writes nothing and opens the server return URL', async () => {
    const createTranslation = vi.fn(async () => {
      throw decisionError()
    })
    const wrapper = mountWith(createTranslation)

    await pickVi(wrapper)
    await wrapper.get('[data-test="blank-decision-return"]').trigger('click')

    expect(createTranslation).toHaveBeenCalledTimes(1)
    expect(assignSpy).toHaveBeenCalledWith('/admin/pages/1/edit?locale=en')
    wrapper.unmount()
  })

  it('Cancel and Escape write nothing and keep the editor as it was', async () => {
    const createTranslation = vi.fn(async () => {
      throw decisionError()
    })
    const wrapper = mountWith(createTranslation)

    await pickVi(wrapper)
    await wrapper.get('[data-test="blank-decision-cancel"]').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-test="blank-translation-dialog"]').exists()).toBe(false)
    expect(document.activeElement?.classList.contains('pb-live-builder-langswitch__toggle')).toBe(true)

    await pickVi(wrapper)
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await flushPromises()
    expect(wrapper.find('[data-test="blank-translation-dialog"]').exists()).toBe(false)

    expect(createTranslation).toHaveBeenCalledTimes(2)
    expect(createTranslation).not.toHaveBeenCalledWith(CREATE_URL, 'vi', 'blank')
    expect(assignSpy).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('hides Return when the server could not generate the content editor URL', async () => {
    const createTranslation = vi.fn(async () => {
      throw new LayoutConflictError('x', 'pb_blank_layout_decision_required', { source_layout: 'empty', locale: 'vi', return_url: null })
    })
    const wrapper = mountWith(createTranslation)

    await pickVi(wrapper)

    expect(wrapper.find('[data-test="blank-decision-return"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('a failed blank create stays in the dialog with the message', async () => {
    const createTranslation = vi
      .fn()
      .mockImplementationOnce(async () => {
        throw decisionError()
      })
      .mockImplementationOnce(async () => {
        throw new Error('Could not create the blank translated layout. Nothing was changed; please try again.')
      })
    const wrapper = mountWith(createTranslation)

    await pickVi(wrapper)
    await wrapper.get('[data-test="blank-decision-create"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-test="blank-translation-dialog"] [role="alert"]').text()).toContain('Nothing was changed')
    expect(assignSpy).not.toHaveBeenCalled()
    wrapper.unmount()
  })
})
