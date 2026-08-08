// Phase 26.40 — Extension API Freeze tripwire.
//
// A single consolidated guard over the FROZEN public extension surface: category order, registry
// determinism, duplicate-handling policy (throw vs overwrite), immutability of reads, and the
// active-vs-reserved source keys. If a frozen Public contract's behavior changes, this breaks loudly.
// Per-registry suites still cover the details; this asserts the guarantees the freeze depends on.

import { describe, it, expect } from 'vitest'

import {
  INSERT_CATEGORY_ORDER,
  InsertProviderRegistry,
  InsertRegistryError,
  makeInsertProvider,
} from '../insert'
import { PatternLibraryRegistry } from '../patterns/library/registry'
import type { PatternDefinition } from '../patterns/library/patternDefinition'
import { LocalTemplateProvider } from '../templates/provider/LocalTemplateProvider'
import { TemplateRuntimeError } from '../templates/runtime/TemplateRuntimeError'
import type { TemplateDefinition } from '../templates/contracts/TemplateDefinition'
import {
  TemplateDistributionRegistry,
  DistributionRegistryError,
  LibraryBackedDistributionProvider,
} from '../templates/distribution'
import {
  DISTRIBUTION_SOURCE,
  ACTIVE_DISTRIBUTION_SOURCES,
} from '../templates/distribution/distributionSource'
import {
  TEMPLATE_SOURCE,
  ACTIVE_TEMPLATE_SOURCES,
  isKnownTemplateSource,
} from '../templates/library/templateSource'

const tpl = (id: string): TemplateDefinition => ({
  id,
  name: id.toUpperCase(),
  layout: { kind: 'tree', tree: { sections: [] } },
})

const pat = (id: string): PatternDefinition => ({ id, name: id.toUpperCase(), tree: { sections: [] } })

/** Minimal TemplateLibraryProvider double for the distribution adapter. */
const emptyLibrary = { all: () => [], find: () => undefined, search: () => [], filterByCategory: () => [], filterBySource: () => [] }

describe('Extension API Freeze — Insert', () => {
  it('freezes the four categories in canonical order', () => {
    expect([...INSERT_CATEGORY_ORDER]).toEqual(['blocks', 'patterns', 'templates', 'assets'])
  })

  it('registry is deterministic and identity-strict (throws on duplicate key)', () => {
    const r = new InsertProviderRegistry()
    r.register(makeInsertProvider('blocks', () => {}))
    r.register(makeInsertProvider('assets', () => {}))
    expect(r.keys()).toEqual(['blocks', 'assets']) // registration order preserved
    expect(() => r.register(makeInsertProvider('blocks', () => {}))).toThrow(InsertRegistryError)
  })
})

describe('Extension API Freeze — Patterns', () => {
  it('PatternLibraryRegistry preserves order, overwrites by id, and duplicate() mints a fresh id', () => {
    const r = new PatternLibraryRegistry()
    r.register(pat('a'))
    r.register(pat('b'))
    expect(r.all().map((p) => p.id)).toEqual(['a', 'b'])

    r.register({ ...pat('a'), name: 'A2' }) // same id → overwrite (last-write-wins)
    expect(r.get('a')?.name).toBe('A2')
    expect(r.all()).toHaveLength(2)

    const copy = r.duplicate('a')
    expect(copy?.id).not.toBe('a') // fresh id
    expect(r.all()).toHaveLength(3)
  })
})

describe('Extension API Freeze — Templates', () => {
  it('LocalTemplateProvider is identity-strict (throws on duplicate registration) and clones reads', () => {
    const p = new LocalTemplateProvider()
    p.register(tpl('t1'))
    expect(() => p.register(tpl('t1'))).toThrow(TemplateRuntimeError)

    const a = p.get('t1')!
    const b = p.get('t1')!
    expect(a).not.toBe(b) // deep-cloned reads → not the same reference
    expect(a).toEqual(b)
  })

  it('TemplateDistributionRegistry is deterministic and throws on duplicate source', () => {
    const reg = new TemplateDistributionRegistry()
    reg.register(new LibraryBackedDistributionProvider(DISTRIBUTION_SOURCE.builtin, emptyLibrary as never))
    reg.register(new LibraryBackedDistributionProvider(DISTRIBUTION_SOURCE.local, emptyLibrary as never))
    expect(reg.sources()).toEqual(['builtin', 'local']) // registration order
    expect(() =>
      reg.register(new LibraryBackedDistributionProvider(DISTRIBUTION_SOURCE.builtin, emptyLibrary as never)),
    ).toThrow(DistributionRegistryError)
  })
})

describe('Extension API Freeze — source keys (active vs reserved)', () => {
  it('freezes the active template + distribution sources; reserved keys stay inert but known', () => {
    expect([...ACTIVE_TEMPLATE_SOURCES]).toEqual(['builtin', 'user', 'imported'])
    expect([...ACTIVE_DISTRIBUTION_SOURCES]).toEqual(['builtin', 'local', 'imported'])

    // Reserved (Experimental) keys are declared and recognized, but NOT active.
    for (const reserved of ['marketplace', 'remote', 'cloud'] as const) {
      expect(isKnownTemplateSource(reserved)).toBe(true)
      expect(TEMPLATE_SOURCE[reserved]).toBe(reserved)
      expect((ACTIVE_TEMPLATE_SOURCES as readonly string[]).includes(reserved)).toBe(false)
    }
  })
})
