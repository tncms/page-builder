// Phase 26.31 — installed template discovery: adoption-aware projection of the installed pack
// ledger over the Template Library. References only, read-only — no definition duplication, no
// runtime mutation, no BuilderState / CommandBus.

import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import {
  InstalledPackRegistry,
  installedPackRegistry,
  type InstalledPack,
} from '../../installation'
import {
  StaticTemplateLibraryProvider,
  builtinTemplateLibrary,
  type TemplateLibraryItem,
} from '../../library'
import {
  projectInstalledTemplates,
  toAdoptionItem,
  AdoptionDiscoveryProvider,
  adoptionDiscovery,
  filterAdoption,
  adoptionPackCounts,
  useAdoptionDiscoveryStore,
} from '..'

function installedPack(over: Partial<InstalledPack> = {}): InstalledPack {
  return {
    id: 'builtin:p',
    packId: 'p',
    source: 'builtin',
    name: 'P',
    category: 'business',
    templateIds: ['hero-basic'],
    ...over,
  }
}

function libItem(over: Partial<TemplateLibraryItem> & { templateId: string }): TemplateLibraryItem {
  return {
    id: `builtin:${over.templateId}`,
    name: `Name ${over.templateId}`,
    category: 'business',
    source: 'builtin',
    ...over,
  }
}

/** A builtin library seeded with the given template ids. */
function seedLibrary(...templateIds: string[]): StaticTemplateLibraryProvider {
  const lib = new StaticTemplateLibraryProvider('builtin')
  for (const templateId of templateIds) lib.register(libItem({ templateId }))
  return lib
}

/** A ledger seeded with the given installed packs. */
function seedLedger(...packs: InstalledPack[]): InstalledPackRegistry {
  const ledger = new InstalledPackRegistry()
  for (const p of packs) ledger.register(p)
  return ledger
}

describe('projectInstalledTemplates', () => {
  it('projects installed packs into deduped reference metadata (no definitions)', () => {
    const items = projectInstalledTemplates([
      installedPack({ packId: 'company', templateIds: ['hero-basic', 'cta-basic'] }),
    ])
    expect(items).toEqual([
      { templateId: 'hero-basic', source: 'builtin', installedFromPack: 'company' },
      { templateId: 'cta-basic', source: 'builtin', installedFromPack: 'company' },
    ])
    // References only — no tree/definition/blocks leak into the projection.
    for (const item of items) {
      expect((item as Record<string, unknown>).tree).toBeUndefined()
      expect((item as Record<string, unknown>).definition).toBeUndefined()
    }
  })

  it('maps a pack source to its definition source (local → user)', () => {
    const items = projectInstalledTemplates([installedPack({ source: 'local', templateIds: ['x'] })])
    expect(items[0].source).toBe('user')
  })

  it('dedupes a template shared by several packs — first pack wins', () => {
    const items = projectInstalledTemplates([
      installedPack({ packId: 'company', templateIds: ['hero-basic'] }),
      installedPack({ id: 'builtin:agency', packId: 'agency', templateIds: ['hero-basic', 'grid'] }),
    ])
    expect(items).toEqual([
      { templateId: 'hero-basic', source: 'builtin', installedFromPack: 'company' },
      { templateId: 'grid', source: 'builtin', installedFromPack: 'agency' },
    ])
  })

  it('skips packs whose source has no definition source (reserved/unknown)', () => {
    const items = projectInstalledTemplates([installedPack({ source: 'marketplace', templateIds: ['x'] })])
    expect(items).toEqual([])
  })
})

describe('toAdoptionItem', () => {
  it('enriches an installed reference with library metadata and marks it available', () => {
    const library = seedLibrary('hero-basic')
    const item = toAdoptionItem(
      { templateId: 'hero-basic', source: 'builtin', installedFromPack: 'company' },
      library,
    )
    expect(item).toMatchObject({
      id: 'builtin:hero-basic',
      templateId: 'hero-basic',
      source: 'builtin',
      installedFromPack: 'company',
      available: true,
      name: 'Name hero-basic',
      category: 'business',
    })
  })

  it('keeps an unresolved reference discoverable but marked unavailable', () => {
    const library = seedLibrary() // empty
    const item = toAdoptionItem({ templateId: 'ghost', source: 'builtin' }, library)
    expect(item).toMatchObject({ id: 'builtin:ghost', templateId: 'ghost', available: false })
    expect(item.name).toBeUndefined()
  })
})

describe('AdoptionDiscoveryProvider', () => {
  it('makes installed pack templates discoverable with provenance + availability', () => {
    const ledger = seedLedger(
      installedPack({ id: 'builtin:company', packId: 'company', templateIds: ['hero-basic', 'cta-basic'] }),
    )
    const library = seedLibrary('hero-basic', 'cta-basic')
    const provider = new AdoptionDiscoveryProvider(ledger, library)

    const all = provider.all()
    expect(all.map((i) => i.id)).toEqual(['builtin:hero-basic', 'builtin:cta-basic'])
    expect(all.every((i) => i.available && i.installedFromPack === 'company')).toBe(true)
    expect(provider.find('builtin:hero-basic')?.name).toBe('Name hero-basic')
    expect(provider.fromPack('company')).toHaveLength(2)
  })

  it('preserves template references and duplicates no definition', () => {
    const ledger = seedLedger(installedPack({ templateIds: ['hero-basic'] }))
    const library = seedLibrary('hero-basic')
    const provider = new AdoptionDiscoveryProvider(ledger, library)

    // The projection points back at the ledger's references; the library still owns the metadata.
    expect(provider.installedTemplates()).toEqual([
      { templateId: 'hero-basic', source: 'builtin', installedFromPack: 'p' },
    ])
    expect(library.find('builtin:hero-basic')?.name).toBe('Name hero-basic') // library untouched
  })

  it('separates available from merely-referenced templates', () => {
    const ledger = seedLedger(installedPack({ templateIds: ['hero-basic', 'ghost'] }))
    const library = seedLibrary('hero-basic') // ghost is referenced but not in the library
    const provider = new AdoptionDiscoveryProvider(ledger, library)

    expect(provider.all()).toHaveLength(2) // both discoverable
    expect(provider.available().map((i) => i.templateId)).toEqual(['hero-basic']) // only one resolves
  })

  it('does not mutate the ledger or library on read, and never applies/executes', () => {
    const ledger = seedLedger(installedPack({ templateIds: ['hero-basic'] }))
    const library = seedLibrary('hero-basic')
    const provider = new AdoptionDiscoveryProvider(ledger, library)

    const before = provider.all()
    before[0].name = 'mutated'
    before[0].templateId = 'mutated'
    expect(provider.all()[0]).toMatchObject({ name: 'Name hero-basic', templateId: 'hero-basic' })
    expect(ledger.all()).toHaveLength(1)
    // A discovery layer: no apply / command / mutation surface.
    expect((provider as unknown as Record<string, unknown>).apply).toBeUndefined()
    expect((provider as unknown as Record<string, unknown>).install).toBeUndefined()
  })
})

describe('adoption filters', () => {
  const library = seedLibrary('hero-basic', 'cta-basic')
  const ledger = seedLedger(
    installedPack({ id: 'builtin:company', packId: 'company', templateIds: ['hero-basic'] }),
    installedPack({ id: 'builtin:agency', packId: 'agency', source: 'local', templateIds: ['cta-basic', 'ghost'] }),
  )
  const items = new AdoptionDiscoveryProvider(ledger, library).all()

  it('filters by pack, source, availability, and query', () => {
    expect(filterAdoption(items, { pack: 'company' }).map((i) => i.templateId)).toEqual(['hero-basic'])
    expect(filterAdoption(items, { source: 'user' })).toHaveLength(2) // agency mapped local → user
    expect(filterAdoption(items, { availableOnly: true }).map((i) => i.templateId)).toEqual(['hero-basic'])
    expect(filterAdoption(items, { query: 'cta' }).map((i) => i.templateId)).toEqual(['cta-basic'])
  })

  it('counts adopted templates per pack', () => {
    expect(adoptionPackCounts(items)).toEqual({ company: 1, agency: 2 })
  })
})

describe('useAdoptionDiscoveryStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    builtinTemplateLibrary.register(libItem({ templateId: 'hero-basic' }))
    installedPackRegistry.register(
      installedPack({ id: 'builtin:company', packId: 'company', templateIds: ['hero-basic'] }),
    )
  })

  afterEach(() => {
    installedPackRegistry.remove('builtin:company')
    builtinTemplateLibrary.remove('builtin:hero-basic')
  })

  it('loads adopted templates read-only and exposes filters/counts', () => {
    const store = useAdoptionDiscoveryStore()
    expect(store.loaded).toBe(false)
    store.load()

    expect(store.loaded).toBe(true)
    expect(store.installedCount).toBe(1)
    expect(store.availableCount).toBe(1)
    expect(store.results.map((i) => i.templateId)).toEqual(['hero-basic'])
    expect(store.packs).toEqual([{ key: 'company', count: 1 }])

    store.setQuery('nope')
    expect(store.noResults).toBe(true)
    store.setQuery('hero')
    expect(store.results).toHaveLength(1)
  })

  it('has no selection/apply/install action (discovery only)', () => {
    const store = useAdoptionDiscoveryStore()
    expect((store as unknown as Record<string, unknown>).apply).toBeUndefined()
    expect((store as unknown as Record<string, unknown>).install).toBeUndefined()
    expect((store as unknown as Record<string, unknown>).select).toBeUndefined()
  })
})
