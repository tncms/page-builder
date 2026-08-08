// Phase 26.33 — template distribution providers: a read-only source-of-origin abstraction that
// ADAPTS the existing library sources. Deterministic ordering, availability, duplicate-strict
// registry — no storage duplication, no runtime.

import { describe, it, expect } from 'vitest'
import { StaticTemplateLibraryProvider, type TemplateLibraryItem } from '../../library'
import {
  LibraryBackedDistributionProvider,
  TemplateDistributionRegistry,
  DistributionRegistryError,
  templateDistribution,
  ACTIVE_DISTRIBUTION_SOURCES,
  isKnownDistributionSource,
} from '..'

function libItem(templateId: string): TemplateLibraryItem {
  return { id: `builtin:${templateId}`, templateId, name: `Name ${templateId}`, category: 'business', source: 'builtin' }
}

/** A library-backed provider over a fresh source seeded with the given template ids. */
function provider(source: string, templateIds: string[], available = true) {
  const library = new StaticTemplateLibraryProvider(source)
  for (const templateId of templateIds) library.register(libItem(templateId))
  return new LibraryBackedDistributionProvider(source, library, () => available)
}

describe('LibraryBackedDistributionProvider', () => {
  it('exposes an existing library source without duplicating storage', () => {
    const library = new StaticTemplateLibraryProvider('builtin')
    const p = new LibraryBackedDistributionProvider('builtin', library)

    expect(p.list()).toEqual([]) // empty until the library has items
    library.register(libItem('hero-basic')) // mutate the UNDERLYING library, not the provider
    expect(p.source()).toBe('builtin')
    expect(p.list().map((i) => i.templateId)).toEqual(['hero-basic']) // delegates live
    expect(p.find('builtin:hero-basic')?.name).toBe('Name hero-basic')
    expect(p.find('builtin:ghost')).toBeNull()
  })

  it('reports availability from its injected check', () => {
    expect(provider('remote', [], false).available()).toBe(false)
    expect(provider('builtin', []).available()).toBe(true)
  })

  it('is a discovery surface only — no resolve/apply/command', () => {
    const p = provider('builtin', ['x'])
    expect((p as unknown as Record<string, unknown>).apply).toBeUndefined()
    expect((p as unknown as Record<string, unknown>).resolve).toBeUndefined()
    expect((p as unknown as Record<string, unknown>).register).toBeUndefined()
  })
})

describe('TemplateDistributionRegistry', () => {
  it('registers providers and looks them up by source', () => {
    const registry = new TemplateDistributionRegistry()
    registry.register(provider('builtin', ['a']))
    registry.register(provider('local', ['b']))
    expect(registry.has('builtin')).toBe(true)
    expect(registry.get('local')?.list().map((i) => i.templateId)).toEqual(['b'])
    expect(registry.get('marketplace')).toBeUndefined()
  })

  it('preserves registration order (deterministic)', () => {
    const registry = new TemplateDistributionRegistry()
    registry.register(provider('builtin', []))
    registry.register(provider('local', []))
    registry.register(provider('imported', []))
    expect(registry.sources()).toEqual(['builtin', 'local', 'imported'])
  })

  it('rejects a duplicate source (identity-strict, never overwrites)', () => {
    const registry = new TemplateDistributionRegistry()
    registry.register(provider('builtin', ['keep']))
    expect(() => registry.register(provider('builtin', ['other']))).toThrow(DistributionRegistryError)
    expect(registry.get('builtin')?.list().map((i) => i.templateId)).toEqual(['keep']) // untouched
  })

  it('filters to available sources', () => {
    const registry = new TemplateDistributionRegistry()
    registry.register(provider('builtin', []))
    registry.register(provider('remote', [], false))
    expect(registry.available().map((p) => p.source())).toEqual(['builtin'])
  })
})

describe('default templateDistribution registry', () => {
  it('wires the three active sources in deterministic order, all available', () => {
    expect(templateDistribution.sources()).toEqual(['builtin', 'local', 'imported'])
    expect(templateDistribution.available().map((p) => p.source())).toEqual(['builtin', 'local', 'imported'])
    expect(templateDistribution.get('marketplace')).toBeUndefined() // reserved, no provider
  })

  it('the builtin provider works — it lists the builtin library source', () => {
    const builtin = templateDistribution.get('builtin')!
    expect(builtin.source()).toBe('builtin')
    expect(Array.isArray(builtin.list())).toBe(true) // adapts the live builtin library
  })

  it('active source keys are the known distribution sources', () => {
    expect(ACTIVE_DISTRIBUTION_SOURCES).toEqual(['builtin', 'local', 'imported'])
    for (const source of ACTIVE_DISTRIBUTION_SOURCES) expect(isKnownDistributionSource(source)).toBe(true)
    expect(isKnownDistributionSource('nope')).toBe(false)
  })
})
