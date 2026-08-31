import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia, type Pinia } from 'pinia'
import BuilderTopBar from '../BuilderTopBar.vue'
import { useEditorStore } from '../../../../editor/stores/editor'
import { useShellStore } from '../../shellStore'

const NAV = { structure: '/admin/page-builder/page/7/edit', live: null, public: 'https://site.test/page', classic: null }

function scene(opts: { dirty?: boolean; selected?: string | null; publicUrlAvailable?: boolean } = {}): Pinia {
  const pinia = createPinia()
  setActivePinia(pinia)
  const editor = useEditorStore()
  editor.$patch({
    boot: { nav: NAV } as never,
    dirty: opts.dirty ?? false,
    selectedComponentId: opts.selected ?? null,
  })
  const shell = useShellStore()
  shell.$patch({
    boot: {
      iframeUrl: 'https://site.test/page-builder/page/7/editor-document',
      publicUrl: NAV.public,
      publicUrlAvailable: opts.publicUrlAvailable ?? true,
    } as never,
  })
  return pinia
}

let assignSpy: ReturnType<typeof vi.spyOn>
let confirmSpy: ReturnType<typeof vi.spyOn>

beforeEach(() => {
  assignSpy = vi.spyOn(window.location, 'assign').mockImplementation(() => {})
  confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true)
})
afterEach(() => {
  assignSpy.mockRestore()
  confirmSpy.mockRestore()
})

describe('BuilderTopBar mode switch (Phase 22.2)', () => {
  it('marks Live Builder as the active mode', () => {
    const wrapper = mount(BuilderTopBar, { global: { plugins: [scene()] } })
    const active = wrapper.get('[aria-current="page"]')
    expect(active.text()).toContain('Live Builder')
  })

  it('no longer hosts the locale switcher (relocated to the left sidebar)', () => {
    const wrapper = mount(BuilderTopBar, { global: { plugins: [scene()] } })
    expect(wrapper.find('.pb-live-builder-langswitch').exists()).toBe(false)
    expect(wrapper.find('[aria-label="Editing language"]').exists()).toBe(false)
  })

  it('links Structure Builder + View Page to the right URLs', () => {
    const wrapper = mount(BuilderTopBar, { global: { plugins: [scene()] } })
    const structure = wrapper.get('[aria-label="Switch to Structure Builder"]')
    expect(structure.attributes('href')).toBe(NAV.structure)
    const view = wrapper.get('[aria-label="View page"]')
    expect(view.attributes('href')).toBe(NAV.public)
    expect(view.attributes('target')).toBe('_blank')
  })

  it('disables View Page when the content entity is unpublished (PB-DRAFT-PREVIEW-R1)', () => {
    const wrapper = mount(BuilderTopBar, { global: { plugins: [scene({ publicUrlAvailable: false })] } })
    // No public link is offered for an unpublished owner…
    expect(wrapper.find('a[aria-label="View page"]').exists()).toBe(false)
    // …instead a disabled control explains it becomes available after publication.
    const disabled = wrapper.get('[aria-label="View page (available after content publication)"]')
    expect(disabled.attributes('aria-disabled')).toBe('true')
    expect(disabled.attributes('href')).toBeUndefined()
    expect(disabled.attributes('title')).toBe('Available after content publication')
  })

  it('carries the selection when switching to Structure while clean', async () => {
    const wrapper = mount(BuilderTopBar, { global: { plugins: [scene({ selected: 'pb-hero' })] } })
    await wrapper.get('[aria-label="Switch to Structure Builder"]').trigger('click')
    expect(confirmSpy).not.toHaveBeenCalled()
    expect(assignSpy).toHaveBeenCalledWith(`${NAV.structure}?selected=pb-hero`)
  })

  it('prompts before leaving when dirty and cancels on decline', async () => {
    confirmSpy.mockReturnValue(false)
    const wrapper = mount(BuilderTopBar, { global: { plugins: [scene({ dirty: true })] } })
    await wrapper.get('[aria-label="Switch to Structure Builder"]').trigger('click')
    expect(confirmSpy).toHaveBeenCalledOnce()
    expect(assignSpy).not.toHaveBeenCalled()
  })
})
