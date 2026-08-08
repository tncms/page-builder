// Phase 26.25 — built-in pack registry: deterministic, validation-first, duplicate-safe, immutable.

import { describe, it, expect } from 'vitest'
import { BuiltinTemplatePackRegistry, BuiltinPackError, type BuiltinTemplatePack } from '..'
import { companyPack } from '../packs/company/companyPack'
import type { TemplateDefinition } from '../../contracts'

function fakeSink() {
  const registered: TemplateDefinition[] = []
  return { registered, register: (d: TemplateDefinition) => (registered.push(d), d) }
}
const badPack: BuiltinTemplatePack = {
  manifest: { id: 'bad', name: '', version: 'x', templates: ['a'] },
  definitions: [{ id: 'a', name: 'A', layout: { kind: 'tree', tree: { sections: [] } } }],
}

describe('BuiltinTemplatePackRegistry', () => {
  it('registers a valid pack: definitions flow to the sink, manifest recorded', () => {
    const sink = fakeSink()
    const registry = new BuiltinTemplatePackRegistry(sink)
    const result = registry.register(companyPack)
    expect(result).toMatchObject({ ok: true, packId: 'company-pack', skipped: false })
    expect(result.templateIds).toEqual(['hero-basic', 'feature-grid-basic', 'cta-basic'])
    expect(sink.registered.map((d) => d.id)).toEqual(['hero-basic', 'feature-grid-basic', 'cta-basic'])
    expect(registry.has('company-pack')).toBe(true)
    expect(registry.packs().map((m) => m.id)).toEqual(['company-pack'])
  })

  it('is idempotent by pack id (re-register is a no-op, no double storage)', () => {
    const sink = fakeSink()
    const registry = new BuiltinTemplatePackRegistry(sink)
    registry.register(companyPack)
    const again = registry.register(companyPack)
    expect(again.skipped).toBe(true)
    expect(sink.registered).toHaveLength(3) // not 6
  })

  it('throws BuiltinPackError on an invalid pack (validation-first — nothing registered)', () => {
    const sink = fakeSink()
    const registry = new BuiltinTemplatePackRegistry(sink)
    let error: unknown
    try {
      registry.register(badPack)
    } catch (e) {
      error = e
    }
    expect(error).toBeInstanceOf(BuiltinPackError)
    expect((error as BuiltinPackError).issues.length).toBeGreaterThan(0)
    expect(sink.registered).toHaveLength(0) // atomic: no partial registration
    expect(registry.has('bad')).toBe(false)
  })

  it('is immutable after freeze (new packs rejected)', () => {
    const registry = new BuiltinTemplatePackRegistry(fakeSink())
    registry.register(companyPack)
    registry.freeze()
    expect(registry.isFrozen).toBe(true)
    // Re-registering an already-known pack still no-ops; a NEW pack is rejected.
    expect(registry.register(companyPack).skipped).toBe(true)
    const other: BuiltinTemplatePack = { ...companyPack, manifest: { ...companyPack.manifest, id: 'other-pack' } }
    expect(() => registry.register(other)).toThrow(BuiltinPackError)
  })
})
