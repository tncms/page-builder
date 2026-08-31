import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import ResourceBrowser from '../ResourceBrowser.vue'
import { useResourceBrowserStore } from '../resourceBrowserStore'
import type { ResourceBrowserItem, ResourceCatalog, ResourceBrowserStrings } from '../types'

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
      item({ rid: 'builtin:section:hero', name: 'Hero', category: 'hero', source: 'builtin' }),
      item({ rid: 'theme:section:banner', name: 'Banner', category: 'cta', source: 'theme' }),
      item({ rid: 'builtin:section:pricing', name: 'Pricing', category: 'cta', available: false }),
    ],
    templates: [item({ rid: 'builtin:page:company', kind: 'template', name: 'Company', category: 'business' })],
  }
}

const strings: ResourceBrowserStrings = {
  title: 'Trình duyệt tài nguyên', patterns: 'Mẫu khối', templates: 'Mẫu trang', search: 'Tìm tài nguyên',
  source: 'Nguồn', category: 'Danh mục', availability: 'Tình trạng', all: 'Tất cả', available: 'Có sẵn',
  unavailable: 'Không khả dụng', sourceBuiltin: 'Tích hợp sẵn', sourceTheme: 'Giao diện', clearFilters: 'Xóa bộ lọc',
  results: 'kết quả', noResults: 'Không có tài nguyên phù hợp', empty: 'Chưa có tài nguyên nào', locked: 'Đã khóa', close: 'Đóng',
}

function open(cat: ResourceCatalog = catalog()) {
  setActivePinia(createPinia())
  const store = useResourceBrowserStore()
  store.setCatalog(cat, strings)
  store.openBrowser('pattern')
  const wrapper = mount(ResourceBrowser)
  return { store, wrapper }
}

beforeEach(() => setActivePinia(createPinia()))

describe('shell structure + localized chrome', () => {
  it('renders a labelled dialog with localized title', () => {
    const { wrapper } = open()
    const dialog = wrapper.get('[role="dialog"]')
    expect(dialog.attributes('aria-label')).toBe('Trình duyệt tài nguyên')
    expect(wrapper.get('.pb-rb__title').text()).toBe('Trình duyệt tài nguyên')
  })

  it('renders Patterns/Templates tabs with the active one selected', () => {
    const { wrapper } = open()
    const tabs = wrapper.findAll('[role="tab"]')
    expect(tabs).toHaveLength(2)
    expect(tabs[0].text()).toBe('Mẫu khối')
    expect(tabs[0].attributes('aria-selected')).toBe('true')
    expect(tabs[1].attributes('aria-selected')).toBe('false')
  })

  it('shows a localized result count', () => {
    const { wrapper } = open()
    expect(wrapper.get('.pb-rb__count').text()).toContain('kết quả')
    expect(wrapper.get('.pb-rb__count').text()).toContain('3')
    expect(wrapper.get('.pb-rb__count').attributes('aria-live')).toBe('polite')
  })
})

describe('kind switch + search + filters', () => {
  it('switching to Templates changes results and resets filters', async () => {
    const { store, wrapper } = open()
    await wrapper.findAll('[role="tab"]')[1].trigger('click')
    expect(store.activeKind).toBe('template')
    expect(wrapper.findAll('.pb-rb__grid-item')).toHaveLength(1)
  })

  it('search filters the grid', async () => {
    const { wrapper } = open()
    await wrapper.get('.pb-rb__search-input').setValue('banner')
    const cards = wrapper.findAll('.pb-rb__grid-item')
    expect(cards).toHaveLength(1)
    expect(cards[0].text()).toContain('Banner')
  })

  it('Clear filters appears only when filtering and resets', async () => {
    const { store, wrapper } = open()
    expect(wrapper.find('.pb-rb__clear').exists()).toBe(false)
    await wrapper.get('.pb-rb__search-input').setValue('zzz')
    expect(store.hasActiveFilters).toBe(true)
    const clear = wrapper.get('.pb-rb__clear')
    expect(clear.text()).toBe('Xóa bộ lọc')
    await clear.trigger('click')
    expect(store.query).toBe('')
    expect(store.hasActiveFilters).toBe(false)
  })
})

describe('states', () => {
  it('shows a distinct empty-catalog state', () => {
    const { wrapper } = open({ patterns: [], templates: [] })
    expect(wrapper.get('[data-testid="empty-state"]').text()).toBe('Chưa có tài nguyên nào')
  })

  it('shows a distinct no-results state when filters match none', async () => {
    const { wrapper } = open()
    await wrapper.get('.pb-rb__search-input').setValue('zzz-nomatch')
    expect(wrapper.get('[data-testid="no-results"]').text()).toBe('Không có tài nguyên phù hợp')
    expect(wrapper.find('[data-testid="empty-state"]').exists()).toBe(false)
  })

  it('marks an unavailable resource with a localized badge', () => {
    const { wrapper } = open()
    const badges = wrapper.findAll('[data-testid="availability-badge"]')
    expect(badges.length).toBe(1)
    expect(badges[0].text()).toContain('Không khả dụng')
  })
})

describe('security + close', () => {
  it('renders malicious metadata as inert text, never markup', () => {
    const { wrapper } = open({
      patterns: [item({ rid: 'x:section:evil', name: '<img src=x onerror=alert(1)>', category: 'hero' })],
      templates: [],
    })
    // No injected element; the payload survives only as escaped text.
    expect(wrapper.find('img[src="x"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>')
  })

  it('Escape and the close button both close the browser', async () => {
    const { store, wrapper } = open()
    await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' })
    expect(store.open).toBe(false)

    store.openBrowser('pattern')
    await wrapper.vm.$nextTick()
    await wrapper.get('.pb-rb__close').trigger('click')
    expect(store.open).toBe(false)
  })
})
