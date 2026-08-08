// Phase 26.28 — starter packs: validation, identity-strict registry, compatibility reuse,
// bundled registration. Packs group templates by reference and never own them.

import { describe, it, expect } from 'vitest'
import {
  validateStarterPack,
  TemplateStarterPackRegistry,
  registerStarterPacks,
  StarterPackError,
  companyStarterPack,
  agencyStarterPack,
  BUILTIN_STARTER_PACKS,
  type TemplateStarterPack,
} from '..'
import type { BuilderEnvironment } from '../../compatibility'

const KNOWN = new Set(['hero-basic', 'feature-grid-basic', 'cta-basic'])
function pack(over: Partial<TemplateStarterPack> = {}): TemplateStarterPack {
  return { id: 'p', name: 'P', version: '1.0.0', category: 'business', templates: ['hero-basic'], ...over }
}

// ---- validation -------------------------------------------------------------

describe('validateStarterPack', () => {
  it('accepts the bundled packs (references resolve against the built-in ids)', () => {
    expect(validateStarterPack(companyStarterPack, { knownTemplateIds: KNOWN })).toEqual([])
    expect(validateStarterPack(agencyStarterPack, { knownTemplateIds: KNOWN })).toEqual([])
  })

  it('flags bad metadata, empty/duplicate refs, and unsafe thumbnail', () => {
    expect(validateStarterPack(pack({ id: '' }))[0].code).toBe('invalid-pack')
    expect(validateStarterPack(pack({ version: '1.0' })).some((i) => i.code === 'invalid-version')).toBe(true)
    expect(validateStarterPack(pack({ category: '' })).some((i) => i.code === 'invalid-category')).toBe(true)
    expect(validateStarterPack(pack({ templates: [] })).some((i) => i.code === 'empty-pack')).toBe(true)
    expect(validateStarterPack(pack({ templates: ['a', 'a'] })).some((i) => i.code === 'duplicate-reference')).toBe(true)
    expect(validateStarterPack(pack({ thumbnail: 'javascript:alert(1)' })).some((i) => i.code === 'unsafe-thumbnail')).toBe(true)
  })

  it('reports missing template references only when known ids are supplied', () => {
    expect(validateStarterPack(pack({ templates: ['ghost'] }))).toEqual([]) // permissive without a known set
    expect(validateStarterPack(pack({ templates: ['ghost'] }), { knownTemplateIds: KNOWN }).some((i) => i.code === 'missing-template')).toBe(true)
  })

  it('reuses the Phase 26.27 compatibility system (builder range)', () => {
    expect(validateStarterPack(pack({ compatibility: { builder: '>=2.0' } })).some((i) => i.code === 'builder-incompatible')).toBe(true)
    expect(validateStarterPack(pack({ compatibility: { builder: '>=1.0' } }))).toEqual([])
  })
})

// ---- registry ---------------------------------------------------------------

describe('TemplateStarterPackRegistry', () => {
  it('registers, finds, lists, and returns immutable clones', () => {
    const registry = new TemplateStarterPackRegistry()
    registry.register(companyStarterPack, { knownTemplateIds: KNOWN })
    registry.register(agencyStarterPack, { knownTemplateIds: KNOWN })
    expect(registry.all().map((p) => p.id)).toEqual(['company-starter-pack', 'agency-starter-pack'])
    expect(registry.find('company-starter-pack')?.name).toBe('Company Starter')

    const first = registry.all()[0]
    first.name = 'MUTATED'
    first.templates.push('x')
    expect(registry.find('company-starter-pack')?.name).toBe('Company Starter')
    expect(registry.find('company-starter-pack')?.templates).toEqual(['hero-basic', 'feature-grid-basic', 'cta-basic'])
  })

  it('rejects duplicate ids (identity-strict) and invalid/incompatible packs', () => {
    const registry = new TemplateStarterPackRegistry()
    registry.register(companyStarterPack)
    expect(() => registry.register(companyStarterPack)).toThrow(StarterPackError)
    expect(() => registry.register(pack({ compatibility: { builder: '>=2.0' } }))).toThrow(StarterPackError)
  })

  it('remove drops the pack (referenced templates untouched — pack owns none)', () => {
    const registry = new TemplateStarterPackRegistry()
    registry.register(companyStarterPack)
    expect(registry.remove('company-starter-pack')).toBe(true)
    expect(registry.has('company-starter-pack')).toBe(false)
    expect(registry.all()).toHaveLength(0)
  })

  it('is immutable after freeze', () => {
    const registry = new TemplateStarterPackRegistry()
    registry.register(companyStarterPack)
    registry.freeze()
    expect(registry.isFrozen).toBe(true)
    expect(() => registry.register(agencyStarterPack)).toThrow(StarterPackError)
  })

  it('surfaces non-blocking compatibility warnings on the result', () => {
    const registry = new TemplateStarterPackRegistry()
    const result = registry.register(pack({ compatibility: { requiredComponents: ['gallery'] } }))
    expect(result.ok).toBe(true)
    expect(result.warnings.some((w) => w.code === 'components-unverified')).toBe(true)
  })
})

// ---- bundled registration ---------------------------------------------------

describe('registerStarterPacks', () => {
  it('registers every bundled pack deterministically and is idempotent', () => {
    const registry = new TemplateStarterPackRegistry()
    registerStarterPacks({ registry, knownTemplateIds: KNOWN })
    registerStarterPacks({ registry, knownTemplateIds: KNOWN }) // idempotent
    expect(registry.all().map((p) => p.id)).toEqual(BUILTIN_STARTER_PACKS.map((p) => p.id))
  })

  it('honors a custom builder environment', () => {
    const registry = new TemplateStarterPackRegistry()
    const env: BuilderEnvironment = { builderVersion: '0.9.0' } // below the packs' '>=1.0'
    expect(() => registerStarterPacks({ registry, environment: env })).toThrow(StarterPackError)
  })
})
