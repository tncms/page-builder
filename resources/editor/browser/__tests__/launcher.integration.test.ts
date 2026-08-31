import { describe, it, expect, beforeEach, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { useResourceBrowserLauncher } from '../useResourceBrowserLauncher'
import { useResourceBrowserStore } from '../resourceBrowserStore'
import { useEditorStore } from '../../stores/editor'
import ResourceBrowser from '../ResourceBrowser.vue'
import App from '../../App.vue'
import BuilderShell from '../../../frontend-editor/shell/components/BuilderShell.vue'

// PB-TEMPLATE-REGISTRY-2-B — the ONE shared browser is reachable from BOTH editor surfaces
// through one launch adapter over one store. This locks the "no second browser authority"
// contract: Structure Builder and Frontend/Live Builder mount the same component + store.

beforeEach(() => setActivePinia(createPinia()))

describe('shared launch adapter', () => {
  it('opens the shared store and lazily loads via the shared editor client', async () => {
    const editor = useEditorStore()
    const client = { getResourceCatalog: vi.fn(async () => ({ patterns: [], templates: [], strings: { patterns: 'P' } })) }
    ;(editor as unknown as { client: unknown }).client = client

    const { open, browser } = useResourceBrowserLauncher()
    await open('template')

    expect(browser.open).toBe(true)
    expect(browser.activeKind).toBe('template')
    expect(client.getResourceCatalog).toHaveBeenCalledTimes(1)
    expect(browser.loaded).toBe(true)
    // The launcher and any consumer share the ONE store instance.
    expect(browser).toBe(useResourceBrowserStore())
  })

  it('does not throw when no editor client is present (isolated shell)', async () => {
    const { open, browser } = useResourceBrowserLauncher()
    await expect(open('pattern')).resolves.toBeUndefined()
    expect(browser.open).toBe(true)
  })
})

describe('both editor shells mount the one shared browser', () => {
  it('Structure Builder (App.vue) renders the shared ResourceBrowser', () => {
    const wrapper = shallowMount(App)
    expect(wrapper.findComponent(ResourceBrowser).exists()).toBe(true)
  })

  it('Frontend/Live Builder (BuilderShell.vue) renders the shared ResourceBrowser', () => {
    const wrapper = shallowMount(BuilderShell)
    expect(wrapper.findComponent(ResourceBrowser).exists()).toBe(true)
  })
})
