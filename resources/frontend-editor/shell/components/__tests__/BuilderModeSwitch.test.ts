import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia, type Pinia } from 'pinia'
import BuilderModeSwitchDialog from '../BuilderModeSwitchDialog.vue'
import BuilderTopBar from '../BuilderTopBar.vue'
import { useEditorStore } from '../../../../editor/stores/editor'

// PB-CORE-AUTHORITY-REMEDIATION-R1 — the render-source switch UI.
const t = (_key: string, fallback: string): string => fallback

function dialog(props: Partial<{ current: 'classic' | 'builder'; target: 'classic' | 'builder'; dirty: boolean; busy: boolean; error: string | null }> = {}) {
  return mount(BuilderModeSwitchDialog, {
    props: { current: 'builder', target: 'classic', dirty: false, busy: false, error: null, t, ...props },
    attachTo: document.body,
  })
}

afterEach(() => {
  document.body.innerHTML = ''
})

describe('BuilderModeSwitchDialog', () => {
  it('states the current and target source and what is kept', () => {
    const wrapper = dialog()
    expect(wrapper.get('[role="dialog"]').attributes('aria-modal')).toBe('true')
    expect(wrapper.get('[data-test="mode-current"]').text()).toBe('Page Builder content')
    expect(wrapper.get('[data-test="mode-target"]').text()).toBe('Classic content')
    expect(wrapper.text()).toContain('kept unchanged')
    expect(wrapper.text()).toContain('switch back at any time')
    wrapper.unmount()
  })

  it('offers Cancel and an explicit switch label when clean (no generic Yes/No)', () => {
    const wrapper = dialog()
    const labels = wrapper.findAll('button').map((b) => b.text())
    expect(labels).toEqual(['Cancel', 'Switch to Classic content'])
    wrapper.unmount()
  })

  it('offers Save, Discard and Cancel when dirty', async () => {
    const wrapper = dialog({ dirty: true })
    const labels = wrapper.findAll('button').map((b) => b.text())
    expect(labels).toEqual(['Cancel', 'Discard changes and switch', 'Save draft and switch'])

    await wrapper.findAll('button')[1].trigger('click')
    await wrapper.findAll('button')[2].trigger('click')
    expect(wrapper.emitted('choose')).toEqual([['discard'], ['save']])
    wrapper.unmount()
  })

  it('focuses Cancel, cancels on Escape and shows an inline error', async () => {
    const wrapper = dialog({ error: 'This page changed in another session.' })
    expect(document.activeElement?.textContent?.trim()).toBe('Cancel')
    expect(wrapper.get('[role="alert"]').text()).toContain('another session')

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    expect(wrapper.emitted('cancel')).toHaveLength(1)
    wrapper.unmount()
  })

  it('removes its key listener on unmount (no duplicate handlers after reuse)', () => {
    const remove = vi.spyOn(document, 'removeEventListener')
    const wrapper = dialog()
    wrapper.unmount()
    expect(remove).toHaveBeenCalledWith('keydown', expect.any(Function), true)
    remove.mockRestore()
  })
})

function scene(opts: { canSwitch?: boolean; mode?: string } = {}): Pinia {
  const pinia = createPinia()
  setActivePinia(pinia)
  useEditorStore().$patch({
    boot: { nav: {}, api: { mode: '/api/page-builder/editor/context/page/7/mode' } } as never,
    permissions: { can_edit: true, can_publish: true, can_switch_mode: opts.canSwitch ?? true },
    layout: { editor_mode: opts.mode ?? 'builder', publication_token: 'tok-1', has_published: true } as never,
  })
  return pinia
}

describe('BuilderTopBar render-source control', () => {
  it('is hidden without switch rights', () => {
    const wrapper = mount(BuilderTopBar, { global: { plugins: [scene({ canSwitch: false })] } })
    expect(wrapper.find('[data-test="mode-switch-open"]').exists()).toBe(false)
  })

  it('opens the confirmation and Cancel writes nothing', async () => {
    const pinia = scene()
    const store = useEditorStore()
    const switchMode = vi.fn()
    store.$patch({ client: { switchMode } as never })
    const wrapper = mount(BuilderTopBar, { global: { plugins: [pinia] }, attachTo: document.body })

    expect(wrapper.get('[data-test="mode-switch-open"]').text()).toBe('Public: Page Builder')
    await wrapper.get('[data-test="mode-switch-open"]').trigger('click')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)

    await wrapper.get('[role="dialog"] button').trigger('click')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(switchMode).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('returns focus to the trigger when the dialog closes', async () => {
    const pinia = scene()
    useEditorStore().$patch({ client: { switchMode: vi.fn() } as never })
    const wrapper = mount(BuilderTopBar, { global: { plugins: [pinia] }, attachTo: document.body })

    await wrapper.get('[data-test="mode-switch-open"]').trigger('click')
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await new Promise((r) => setTimeout(r, 0))

    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(document.activeElement?.getAttribute('data-test')).toBe('mode-switch-open')
    wrapper.unmount()
  })

  it('switches with the held publication token and adopts the new state', async () => {
    const pinia = scene()
    const store = useEditorStore()
    const switchMode = vi.fn(async () => ({ editor_mode: 'classic', publication_token: 'tok-2', has_published: true }))
    store.$patch({ client: { switchMode } as never })
    const wrapper = mount(BuilderTopBar, { global: { plugins: [pinia] }, attachTo: document.body })

    await wrapper.get('[data-test="mode-switch-open"]').trigger('click')
    const buttons = wrapper.findAll('[role="dialog"] button')
    await buttons[buttons.length - 1].trigger('click')
    await new Promise((r) => setTimeout(r, 0))

    expect(switchMode).toHaveBeenCalledWith('classic', 'tok-1')
    expect(store.layout?.editor_mode).toBe('classic')
    expect(store.layout?.publication_token).toBe('tok-2')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('keeps the dialog open with the server message on a refusal', async () => {
    const pinia = scene()
    const store = useEditorStore()
    store.$patch({ client: { switchMode: vi.fn(async () => { throw new Error('This page changed in another session.') }) } as never })
    const wrapper = mount(BuilderTopBar, { global: { plugins: [pinia] }, attachTo: document.body })

    await wrapper.get('[data-test="mode-switch-open"]').trigger('click')
    const buttons = wrapper.findAll('[role="dialog"] button')
    await buttons[buttons.length - 1].trigger('click')
    await new Promise((r) => setTimeout(r, 0))

    expect(wrapper.get('[role="alert"]').text()).toContain('another session')
    expect(store.layout?.editor_mode).toBe('builder')
    wrapper.unmount()
  })
})
