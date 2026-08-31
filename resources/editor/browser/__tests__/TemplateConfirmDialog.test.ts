import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import TemplateConfirmDialog from '../TemplateConfirmDialog.vue'
import { useResourceBrowserStore } from '../resourceBrowserStore'
import type { ResourceBrowserItem, ResourceCatalog, ResourceMaterializer } from '../types'

// PB-TEMPLATE-REGISTRY-2-C — the two-stage Template replacement confirmation. Its copy
// truthfully states the DRAFT is replaced and the PUBLISHED page is unchanged until
// Publish. Cancel closes with ZERO apply request; Confirm applies once; a pending apply
// disables Confirm; Escape cancels (never confirms).

function item(over: Partial<ResourceBrowserItem>): ResourceBrowserItem {
  return {
    rid: over.rid ?? 'builtin:page:company', kind: over.kind ?? 'template', source: over.source ?? 'builtin',
    category: over.category ?? 'business', tags: over.tags ?? [], name: over.name ?? 'Company',
    description: over.description ?? null, preview: over.preview ?? null,
    available: over.available ?? true, locked: over.locked ?? false, requiredCapability: over.requiredCapability ?? null,
  }
}

function catalog(): ResourceCatalog {
  return { patterns: [], templates: [item({})] }
}

function materializer(over: Partial<ResourceMaterializer> = {}): ResourceMaterializer {
  return {
    insertPattern: vi.fn(async () => ({ status: 'inserted' as const })),
    applyTemplate: vi.fn(async () => ({ status: 'applied' as const })),
    ...over,
  }
}

function scene(strings?: Record<string, string>) {
  setActivePinia(createPinia())
  const store = useResourceBrowserStore()
  store.setCatalog(catalog(), strings)
  store.bindMaterializer(materializer())
  store.openBrowser('template')
  store.setKind('template')
  store.select('builtin:page:company')
  store.requestTemplate() // stage-2 open
  const wrapper = mount(TemplateConfirmDialog)
  return { store, wrapper }
}

beforeEach(() => setActivePinia(createPinia()))

describe('truthful destructive copy', () => {
  it('renders a labelled modal dialog stating draft-replaced / published-unchanged', () => {
    const { wrapper } = scene()
    const dialog = wrapper.get('[role="dialog"]')
    expect(dialog.attributes('aria-modal')).toBe('true')
    const text = wrapper.text().toLowerCase()
    expect(text).toContain('draft')
    expect(text).toContain('publish')
  })

  it('uses server-provided (Admin UI locale) confirmation strings', () => {
    const { wrapper } = scene({ confirmReplace: 'Thay thế bản nháp', confirmCancel: 'Giữ bản nháp' })
    expect(wrapper.get('[data-testid="confirm-apply"]').text()).toContain('Thay thế bản nháp')
    expect(wrapper.get('[data-testid="confirm-cancel"]').text()).toContain('Giữ bản nháp')
  })
})

describe('cancel sends zero request', () => {
  it('Cancel closes the confirmation and never calls applyTemplate', async () => {
    const { store, wrapper } = scene()
    const apply = store.materializer!.applyTemplate as ReturnType<typeof vi.fn>
    await wrapper.get('[data-testid="confirm-cancel"]').trigger('click')
    expect(store.isTemplateConfirmOpen).toBe(false)
    expect(apply).not.toHaveBeenCalled()
  })

  it('Escape cancels — never confirms', async () => {
    const { store, wrapper } = scene()
    const apply = store.materializer!.applyTemplate as ReturnType<typeof vi.fn>
    await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' })
    expect(store.isTemplateConfirmOpen).toBe(false)
    expect(apply).not.toHaveBeenCalled()
  })
})

describe('confirm applies once', () => {
  it('Confirm delegates to the store apply exactly once', async () => {
    const { store, wrapper } = scene()
    const spy = vi.spyOn(store, 'confirmTemplate')
    await wrapper.get('[data-testid="confirm-apply"]').trigger('click')
    expect(spy).toHaveBeenCalledTimes(1)
  })

  it('disables Confirm while an apply is pending (no double submit)', async () => {
    const { store, wrapper } = scene()
    store.actionPending = true
    await wrapper.vm.$nextTick()
    expect(wrapper.get('[data-testid="confirm-apply"]').attributes('disabled')).toBeDefined()
  })
})
