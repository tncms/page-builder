// Phase 26.29 — pack discovery: projection, provider, store. Projection only — no install/apply,
// no template definitions carried, existing template discovery untouched.

import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

import {
  toPackLibraryItem,
  packLibraryItemId,
  StarterPackLibraryProvider,
  usePackDiscoveryStore,
  type TemplatePackLibraryItem,
} from '..'
import {
  TemplateStarterPackRegistry,
  registerStarterPacks,
  companyStarterPack,
  agencyStarterPack,
  type TemplateStarterPack,
} from '../../starterPacks'

function pack(over: Partial<TemplateStarterPack> = {}): TemplateStarterPack {
  return { id: 'p', name: 'P', version: '1.0.0', category: 'business', templates: ['hero-basic'], ...over }
}

// ---- projection -------------------------------------------------------------

describe('toPackLibraryItem', () => {
  it('projects metadata + template COUNT, never the referenced templates', () => {
    const item = toPackLibraryItem(companyStarterPack)
    expect(item).toMatchObject({
      id: 'builtin:company-starter-pack',
      packId: 'company-starter-pack',
      name: 'Company Starter',
      category: 'business',
      source: 'builtin',
      templateCount: 3,
    })
    expect((item as Record<string, unknown>).templates).toBeUndefined() // no template ids/definitions leak
    expect((item as Record<string, unknown>).compatibility).toBeUndefined()
  })

  it('defaults the source and keeps only a safe thumbnail URL', () => {
    expect(toPackLibraryItem(pack({ source: undefined })).source).toBe('builtin')
    expect(toPackLibraryItem(pack({ thumbnail: 'https://cdn.example.com/p.webp' })).thumbnail).toBe('https://cdn.example.com/p.webp')
    expect(toPackLibraryItem(pack({ thumbnail: 'javascript:alert(1)' })).thumbnail).toBeUndefined()
    expect(packLibraryItemId('local', 'x')).toBe('local:x')
  })
})

// ---- provider ---------------------------------------------------------------

describe('StarterPackLibraryProvider', () => {
  function seeded() {
    const registry = new TemplateStarterPackRegistry()
    registry.register(companyStarterPack)
    registry.register(agencyStarterPack)
    return new StarterPackLibraryProvider(registry)
  }

  it('projects the registry and browses/searches/filters', () => {
    const provider = seeded()
    expect(provider.all().map((i) => i.id)).toEqual(['builtin:company-starter-pack', 'builtin:agency-starter-pack'])
    expect(provider.find('builtin:agency-starter-pack')?.name).toBe('Agency Starter')
    expect(provider.search('company').map((i) => i.packId)).toEqual(['company-starter-pack'])
    expect(provider.filterByCategory('agency').map((i) => i.packId)).toEqual(['agency-starter-pack'])
    expect(provider.filterBySource('builtin')).toHaveLength(2)
    expect(provider.filterBySource('local')).toHaveLength(0)
  })

  it('reflects the registry live (no duplicate storage)', () => {
    const registry = new TemplateStarterPackRegistry()
    const provider = new StarterPackLibraryProvider(registry)
    expect(provider.all()).toHaveLength(0)
    registry.register(companyStarterPack)
    expect(provider.all().map((i) => i.packId)).toEqual(['company-starter-pack'])
  })
})

// ---- store ------------------------------------------------------------------

describe('usePackDiscoveryStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    registerStarterPacks() // into the default registry the store's provider reads
  })

  it('loads projected packs and derives chip counts', () => {
    const store = usePackDiscoveryStore()
    expect(store.loaded).toBe(false)
    store.load()
    expect(store.results.map((i) => i.packId)).toEqual(['company-starter-pack', 'agency-starter-pack'])
    expect(store.sources).toEqual(expect.arrayContaining([{ key: 'builtin', count: 2 }]))
    expect(store.categories).toEqual(expect.arrayContaining([{ key: 'business', count: 1 }, { key: 'agency', count: 1 }]))
  })

  it('searches and filters by category/source', () => {
    const store = usePackDiscoveryStore()
    store.load()
    store.setQuery('agency')
    expect(store.results.map((i) => i.packId)).toEqual(['agency-starter-pack'])
    store.setQuery('')
    store.setCategory('business')
    expect(store.results.map((i) => i.packId)).toEqual(['company-starter-pack'])
    store.setCategory(null)
    store.setSource('local')
    expect(store.results).toHaveLength(0)
    expect(store.noResults).toBe(true)
  })
})
