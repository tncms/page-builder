import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useResourceBrowserStore } from '../resourceBrowserStore'
import type { ResourceBrowserItem, ResourceCatalog } from '../types'

// PB-TEMPLATE-REGISTRY-2-B — the ONE shared, presentation-only Resource Browser state
// authority. It owns kind/query/filters/selection/counts over an injected metadata
// catalog; it does NOT fetch localization, compute availability, hold payloads, or
// touch the layout/CAS. Data-agnostic: fed via setCatalog (or load()).

function item(over: Partial<ResourceBrowserItem>): ResourceBrowserItem {
  return {
    rid: over.rid ?? 'builtin:section:x',
    kind: over.kind ?? 'pattern',
    source: over.source ?? 'builtin',
    category: over.category ?? 'hero',
    tags: over.tags ?? [],
    name: over.name ?? 'X',
    description: over.description ?? null,
    preview: over.preview ?? null,
    available: over.available ?? true,
    locked: over.locked ?? false,
    requiredCapability: over.requiredCapability ?? null,
  }
}

function catalog(): ResourceCatalog {
  return {
    patterns: [
      item({ rid: 'builtin:section:hero', name: 'Hero', category: 'hero', source: 'builtin', tags: ['hero', 'dark'] }),
      item({ rid: 'theme:section:banner', name: 'Banner', category: 'cta', source: 'theme', tags: ['cta'], description: 'A themed banner' }),
      item({ rid: 'builtin:section:pricing', name: 'Pricing', category: 'cta', source: 'builtin', available: false }),
    ],
    templates: [
      item({ rid: 'builtin:page:company', kind: 'template', name: 'Company', category: 'business', source: 'builtin' }),
    ],
  }
}

function scene() {
  setActivePinia(createPinia())
  const store = useResourceBrowserStore()
  store.setCatalog(catalog())
  return store
}

beforeEach(() => setActivePinia(createPinia()))

describe('defaults + kind separation', () => {
  it('defaults to patterns, no filters, all results', () => {
    const store = scene()
    expect(store.activeKind).toBe('pattern')
    expect(store.query).toBe('')
    expect(store.hasActiveFilters).toBe(false)
    expect(store.count).toBe(3)
    expect(store.results.map((r) => r.rid)).toEqual(['builtin:section:hero', 'theme:section:banner', 'builtin:section:pricing'])
  })

  it('separates patterns and templates by invariant kind', () => {
    const store = scene()
    expect(store.results.every((r) => r.kind === 'pattern')).toBe(true)
    store.setKind('template')
    expect(store.count).toBe(1)
    expect(store.results[0].rid).toBe('builtin:page:company')
  })

  it('switching kind resets query and filters (one documented rule)', () => {
    const store = scene()
    store.setQuery('hero')
    store.setSource('builtin')
    store.setKind('template')
    expect(store.query).toBe('')
    expect(store.source).toBe('all')
    expect(store.hasActiveFilters).toBe(false)
  })
})

describe('localized search', () => {
  it('matches resolved name/description/category/tags, trimmed + case-insensitive', () => {
    const store = scene()
    store.setQuery('  BANNER ')
    expect(store.results.map((r) => r.rid)).toEqual(['theme:section:banner'])
    store.setQuery('themed')
    expect(store.results.map((r) => r.rid)).toEqual(['theme:section:banner']) // description
    store.setQuery('dark')
    expect(store.results.map((r) => r.rid)).toEqual(['builtin:section:hero']) // tag
    store.setQuery('cta')
    expect(store.results.map((r) => r.rid)).toEqual(['theme:section:banner', 'builtin:section:pricing']) // category
  })

  it('empty query returns all for the active kind + filters', () => {
    const store = scene()
    store.setQuery('zzz-none')
    expect(store.count).toBe(0)
    store.setQuery('')
    expect(store.count).toBe(3)
  })

  it('re-labelling the catalog (locale reinit) yields no stale results', () => {
    const store = scene()
    store.setQuery('hero')
    expect(store.count).toBe(1)
    // Simulate an Admin-UI-locale reinit: same rids, translated names.
    store.setCatalog({
      patterns: [item({ rid: 'builtin:section:hero', name: 'Khoi Hero', category: 'hero' })],
      templates: [],
    })
    expect(store.results.map((r) => r.name)).toEqual(['Khoi Hero'])
  })
})

describe('filter composition + counts + reset', () => {
  it('composes source + category + availability deterministically', () => {
    const store = scene()
    store.setSource('builtin')
    expect(store.results.map((r) => r.rid)).toEqual(['builtin:section:hero', 'builtin:section:pricing'])
    store.setCategory('cta')
    expect(store.results.map((r) => r.rid)).toEqual(['builtin:section:pricing'])
    store.setAvailability('available')
    expect(store.count).toBe(0) // pricing is unavailable
    store.setAvailability('unavailable')
    expect(store.results.map((r) => r.rid)).toEqual(['builtin:section:pricing'])
  })

  it('unavailable items remain present under All/Unavailable', () => {
    const store = scene()
    expect(store.results.some((r) => r.rid === 'builtin:section:pricing')).toBe(true)
    store.setAvailability('available')
    expect(store.results.some((r) => r.rid === 'builtin:section:pricing')).toBe(false)
  })

  it('derives filter options from invariant ids for the active kind', () => {
    const store = scene()
    expect(store.sourceOptions.map((o) => o.value)).toEqual(['builtin', 'theme'])
    expect(store.categoryOptions.map((o) => o.value)).toEqual(['cta', 'hero'])
  })

  it('reset restores the one documented default state', () => {
    const store = scene()
    store.setQuery('x'); store.setSource('theme'); store.setCategory('cta'); store.setAvailability('unavailable')
    expect(store.hasActiveFilters).toBe(true)
    store.resetFilters()
    expect(store.query).toBe('')
    expect(store.source).toBe('all')
    expect(store.category).toBe('all')
    expect(store.availability).toBe('all')
    expect(store.hasActiveFilters).toBe(false)
  })
})

describe('empty vs no-results distinction', () => {
  it('distinguishes an empty catalog from a filtered no-result', () => {
    const store = scene()
    expect(store.isEmptyKind).toBe(false)
    store.setQuery('zzz')
    expect(store.isEmptyKind).toBe(false)
    expect(store.isNoResults).toBe(true)
    store.setCatalog({ patterns: [], templates: [] })
    expect(store.isEmptyKind).toBe(true)
    expect(store.isNoResults).toBe(false)
  })
})

describe('selection + payload boundary + immutability', () => {
  it('selection is rid-based and stable', () => {
    const store = scene()
    store.select('theme:section:banner')
    expect(store.selectedRid).toBe('theme:section:banner')
    expect(store.selectedItem?.rid).toBe('theme:section:banner')
  })

  it('never requires or holds a resource payload', () => {
    const store = scene()
    for (const r of store.results) {
      expect(r).not.toHaveProperty('payload')
      expect(r).not.toHaveProperty('sections')
    }
  })

  it('load() delegates to the client and surfaces a safe error', async () => {
    setActivePinia(createPinia())
    const store = useResourceBrowserStore()
    const client = { getResourceCatalog: vi.fn(async () => catalog()) }
    await store.load(client as never)
    expect(client.getResourceCatalog).toHaveBeenCalledTimes(1)
    expect(store.loaded).toBe(true)
    expect(store.count).toBe(3)

    // Failure surfaces a safe message, not raw internals.
    setActivePinia(createPinia())
    const store2 = useResourceBrowserStore()
    const bad = { getResourceCatalog: vi.fn(async () => { throw new Error('/abs/path leaked') }) }
    await store2.load(bad as never)
    expect(store2.error).toBeTruthy()
    expect(store2.loaded).toBe(false)
  })
})
