// Phase 26.17 — LocalTemplateProvider + resolveTemplate.

import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

import { LocalTemplateProvider, localTemplateProvider, templateProvider } from '../../provider'
import { resolveTemplate } from '../resolveTemplate'
import { TemplateRuntimeError } from '../TemplateRuntimeError'
import { patternProvider } from '../../../patterns/provider'
import { patternLibraryRegistry, createPatternDefinition, type PatternTree } from '../../../patterns/library'
import type { TemplateDefinition } from '../../contracts'
import type { SectionNode } from '../../../types'

function richSection(): SectionNode {
  return {
    type: 'section',
    data: { background: '#fff', padding: { desktop: '{{spacing.lg}}', mobile: '{{spacing.sm}}' }, states: { hover: { background: '{{color.primary}}' } } },
    blocks: [{ type: 'heading', data: { color: '{{color.text}}' } }],
  }
}
function pageTree(): PatternTree {
  return createPatternDefinition({ name: 'x', sections: [richSection()] }).tree
}
function tpl(over: Partial<TemplateDefinition> = {}): TemplateDefinition {
  return { id: 't1', name: 'T1', layout: { kind: 'tree', tree: pageTree() }, ...over }
}

beforeEach(() => {
  setActivePinia(createPinia())
  patternLibraryRegistry.clear()
  localTemplateProvider.clear()
})

// ---- LocalTemplateProvider ---------------------------------------------------

describe('LocalTemplateProvider', () => {
  it('is the exported active provider', () => {
    expect(templateProvider).toBe(localTemplateProvider)
    expect(localTemplateProvider).toBeInstanceOf(LocalTemplateProvider)
  })

  it('registers, gets, lists and updates (id + position preserved)', () => {
    localTemplateProvider.register(tpl({ id: 'a', name: 'A' }))
    localTemplateProvider.register(tpl({ id: 'b', name: 'B' }))
    expect(localTemplateProvider.all().map((t) => t.id)).toEqual(['a', 'b'])
    expect(localTemplateProvider.get('a')?.name).toBe('A')

    const updated = localTemplateProvider.update('a', { name: 'A2' })
    expect(updated?.id).toBe('a')
    expect(localTemplateProvider.all().map((t) => t.id)).toEqual(['a', 'b']) // position kept
    expect(localTemplateProvider.update('missing', { name: 'x' })).toBeUndefined()
  })

  it('protects against duplicate id registration', () => {
    localTemplateProvider.register(tpl({ id: 'a' }))
    expect(() => localTemplateProvider.register(tpl({ id: 'a' }))).toThrow(TemplateRuntimeError)
    try {
      localTemplateProvider.register(tpl({ id: 'a' }))
    } catch (e) {
      expect((e as TemplateRuntimeError).code).toBe('duplicate_registration')
    }
  })

  it('returns immutable reads — callers cannot mutate stored data', () => {
    const input = tpl({ id: 'a', name: 'A' })
    localTemplateProvider.register(input)
    input.name = 'mutated after register' // must not affect the store
    expect(localTemplateProvider.get('a')?.name).toBe('A')

    const read = localTemplateProvider.get('a')!
    read.name = 'mutated read'
    expect(localTemplateProvider.get('a')?.name).toBe('A') // read was a clone
  })

  it('remove works and is independent of the pattern registry', () => {
    patternLibraryRegistry.register(createPatternDefinition({ name: 'P', sections: [richSection()] }))
    localTemplateProvider.register(tpl({ id: 'a' }))
    expect(localTemplateProvider.remove('a')).toBe(true)
    expect(localTemplateProvider.remove('missing')).toBe(false)
    expect(patternProvider.all()).toHaveLength(1) // untouched
  })
})

// ---- resolveTemplate: inline tree --------------------------------------------

describe('resolveTemplate — tree layout', () => {
  it('resolves an inline tree, preserving tokens/responsive/state and cloning independently', () => {
    const template = tpl()
    const resolved = resolveTemplate(template)
    expect(resolved.source).toBe('tree')
    expect(resolved.patternIds).toEqual([])
    const data = resolved.tree.sections[0].data
    expect(data.padding).toEqual({ desktop: '{{spacing.lg}}', mobile: '{{spacing.sm}}' })
    expect(data.states).toEqual({ hover: { background: '{{color.primary}}' } })

    // Independent clone — mutating the result never touches the template.
    ;(resolved.tree.sections[0].data as Record<string, unknown>).background = '#000'
    const layout = template.layout
    if (layout.kind === 'tree') expect(layout.tree.sections[0].data.background).toBe('#fff')
  })

  it('rejects an empty or invalid tree', () => {
    expect(() => resolveTemplate(tpl({ layout: { kind: 'tree', tree: { sections: [] } } }))).toThrow(/empty/i)
    expect(() => resolveTemplate(tpl({ layout: { kind: 'tree', tree: {} as PatternTree } }))).toThrow(TemplateRuntimeError)
  })
})

// ---- resolveTemplate: pattern composition ------------------------------------

describe('resolveTemplate — patterns layout', () => {
  it('resolves + merges patterns in declared order, cloned from the registry', () => {
    const hero = patternProvider.register(createPatternDefinition({ name: 'Hero', sections: [richSection()] }))
    const cta = patternProvider.register(createPatternDefinition({ name: 'CTA', sections: [{ type: 'cta', data: {}, blocks: [] }] }))

    const resolved = resolveTemplate(tpl({ layout: { kind: 'patterns', patternIds: [cta.id, hero.id] } }))
    expect(resolved.source).toBe('patterns')
    expect(resolved.patternIds).toEqual([cta.id, hero.id])
    expect(resolved.tree.sections.map((s) => s.type)).toEqual(['cta', 'section']) // order preserved

    // Cloned — mutating the result never touches the pattern in the registry.
    ;(resolved.tree.sections[1].data as Record<string, unknown>).background = '#000'
    expect(patternProvider.get(hero.id)?.tree.sections[0].data.background).toBe('#fff')
  })

  it('fails atomically on a missing pattern (no partial result)', () => {
    const hero = patternProvider.register(createPatternDefinition({ name: 'Hero', sections: [richSection()] }))
    try {
      resolveTemplate(tpl({ layout: { kind: 'patterns', patternIds: [hero.id, 'nope', 'gone'] } }))
      throw new Error('should have thrown')
    } catch (e) {
      expect(e).toBeInstanceOf(TemplateRuntimeError)
      expect((e as TemplateRuntimeError).code).toBe('missing_pattern')
      expect((e as TemplateRuntimeError).details?.missing).toEqual(['nope', 'gone'])
    }
  })

  it('rejects an empty pattern list and an unknown kind', () => {
    expect(() => resolveTemplate(tpl({ layout: { kind: 'patterns', patternIds: [] } }))).toThrow(/no patterns/i)
    expect(() => resolveTemplate(tpl({ layout: { kind: 'bogus' } as never }))).toThrow(TemplateRuntimeError)
  })

  it('uses an injected pattern provider when given', () => {
    const p = createPatternDefinition({ name: 'Injected', sections: [richSection()] })
    const injected = { get: (id: string) => (id === p.id ? p : undefined) } as never
    const resolved = resolveTemplate(tpl({ layout: { kind: 'patterns', patternIds: [p.id] } }), { patternProvider: injected })
    expect(resolved.tree.sections).toHaveLength(1)
  })
})
