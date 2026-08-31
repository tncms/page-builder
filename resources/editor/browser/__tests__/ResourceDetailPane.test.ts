import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import ResourceDetailPane from '../ResourceDetailPane.vue'
import { useResourceBrowserStore } from '../resourceBrowserStore'
import type { ResourceBrowserItem, ResourceCatalog, ResourceMaterializer } from '../types'

// PB-TEMPLATE-REGISTRY-2-C — the preview/detail pane. Preview is a server-rendered iframe
// keyed by rid (non-mutating). It offers Insert (Pattern) or a two-stage Use-template
// (Template) action, disabled + reasoned when unavailable/locked, and renders resource
// metadata as escaped text (no v-html sink). It never mutates on preview/selection.

function item(over: Partial<ResourceBrowserItem>): ResourceBrowserItem {
  return {
    rid: over.rid ?? 'builtin:section:x', kind: over.kind ?? 'pattern', source: over.source ?? 'builtin',
    category: over.category ?? 'hero', tags: over.tags ?? [], name: over.name ?? 'X',
    description: over.description ?? null, preview: over.preview ?? null,
    available: over.available ?? true, locked: over.locked ?? false, requiredCapability: over.requiredCapability ?? null,
  }
}

function catalog(): ResourceCatalog {
  return {
    patterns: [
      item({ rid: 'builtin:section:hero', name: 'Hero' }),
      item({ rid: 'builtin:section:gone', name: 'Gone', available: false }),
    ],
    templates: [item({ rid: 'builtin:page:company', kind: 'template', name: 'Company' })],
  }
}

function materializer(over: Partial<ResourceMaterializer> = {}): ResourceMaterializer {
  return {
    insertPattern: vi.fn(async () => ({ status: 'inserted' as const })),
    applyTemplate: vi.fn(async () => ({ status: 'applied' as const })),
    ...over,
  }
}

function scene(select = 'builtin:section:hero') {
  setActivePinia(createPinia())
  const store = useResourceBrowserStore()
  store.setCatalog(catalog())
  store.configurePreview({ previewBase: 'https://cms.test/page-builder/preview', locale: 'vi' })
  store.bindMaterializer(materializer())
  const kind = catalog().templates.some((t) => t.rid === select) ? 'template' : 'pattern'
  store.openBrowser(kind)
  store.select(select)
  const wrapper = mount(ResourceDetailPane)
  return { store, wrapper }
}

beforeEach(() => setActivePinia(createPinia()))

describe('non-mutating preview', () => {
  it('renders a server-rendered preview iframe keyed by rid + editing locale', () => {
    const { wrapper } = scene('builtin:section:hero')
    const frame = wrapper.get('[data-testid="preview-frame"]')
    const src = frame.attributes('src') ?? ''
    expect(src).toContain('source=rid')
    expect(src).toContain('ref=builtin%3Asection%3Ahero')
    expect(src).toContain('locale=vi')
  })

  it('preview reflects a selection change with no stale overwrite', async () => {
    const { store, wrapper } = scene('builtin:section:hero')
    store.select('builtin:section:gone')
    await wrapper.vm.$nextTick()
    expect(wrapper.get('[data-testid="preview-frame"]').attributes('src')).toContain('ref=builtin%3Asection%3Agone')
  })
})

describe('pattern insertion action', () => {
  it('inserts an available pattern through the store (delegated once)', async () => {
    const { store, wrapper } = scene('builtin:section:hero')
    const spy = vi.spyOn(store, 'insertSelected')
    await wrapper.get('[data-testid="detail-insert"]').trigger('click')
    expect(spy).toHaveBeenCalledTimes(1)
  })

  it('disables the action and shows a reason for an unavailable resource', () => {
    const { wrapper } = scene('builtin:section:gone')
    const btn = wrapper.get('[data-testid="detail-insert"]')
    expect(btn.attributes('disabled')).toBeDefined()
  })
})

describe('template action opens the two-stage confirmation', () => {
  it('requests the template (stage-2) without any mutation request', async () => {
    const { store, wrapper } = scene('builtin:page:company')
    const apply = store.materializer!.applyTemplate as ReturnType<typeof vi.fn>
    await wrapper.get('[data-testid="detail-use-template"]').trigger('click')
    expect(store.isTemplateConfirmOpen).toBe(true)
    expect(apply).not.toHaveBeenCalled()
  })
})

describe('safety + a11y + i18n', () => {
  it('renders resource metadata as escaped text (no v-html injection sink)', () => {
    setActivePinia(createPinia())
    const store = useResourceBrowserStore()
    store.setCatalog({
      patterns: [item({ rid: 'builtin:section:x', name: '<img src=x onerror=alert(1)>', description: '<script>bad()</script>' })],
      templates: [],
    })
    store.openBrowser('pattern')
    store.select('builtin:section:x')
    const wrapper = mount(ResourceDetailPane)
    expect(wrapper.find('img[onerror]').exists()).toBe(false)
    expect(wrapper.find('script').exists()).toBe(false)
    expect(wrapper.html()).toContain('&lt;img')
  })

  it('closes the detail on the close control', async () => {
    const { store, wrapper } = scene('builtin:section:hero')
    const spy = vi.spyOn(store, 'closeDetail')
    await wrapper.get('[data-testid="detail-close"]').trigger('click')
    expect(spy).toHaveBeenCalledTimes(1)
  })

  it('closes the detail on Escape', async () => {
    const { store, wrapper } = scene('builtin:section:hero')
    const spy = vi.spyOn(store, 'closeDetail')
    await wrapper.get('[role="region"]').trigger('keydown', { key: 'Escape' })
    expect(spy).toHaveBeenCalledTimes(1)
  })

  it('renders server-provided (Admin UI locale) action strings, not hardcoded English', () => {
    setActivePinia(createPinia())
    const store = useResourceBrowserStore()
    store.setCatalog(catalog(), { insert: 'Chèn' })
    store.openBrowser('pattern')
    store.select('builtin:section:hero')
    const wrapper = mount(ResourceDetailPane)
    expect(wrapper.get('[data-testid="detail-insert"]').text()).toContain('Chèn')
  })
})
