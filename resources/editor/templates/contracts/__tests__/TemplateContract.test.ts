// Phase 26.16 — Template contract tests (contracts only, NO runtime).
//
// Verifies the TemplateDefinition/TemplateProvider contracts are consistent and
// implementable, that a template's layout reuses the Pattern tree contract (proving
// Templates depend on Patterns), and that the Pattern v1 contracts are unchanged.

import { describe, it, expect } from 'vitest'

import {
  isTemplateTreeLayout,
  isTemplatePatternsLayout,
  type TemplateDefinition,
  type TemplateProvider,
  type TemplateLayoutRef,
} from '../index'
import { createPatternDefinition, patternLibraryRegistry, type PatternTree } from '../../../patterns/library'
import { patternProvider } from '../../../patterns/provider'
import type { SectionNode } from '../../../types'

function pageTree(): PatternTree {
  const section: SectionNode = { type: 'section', data: {}, blocks: [{ type: 'heading', data: {} }] }
  return createPatternDefinition({ name: 'x', sections: [section] }).tree // reuse the Pattern tree contract
}

// ---- TemplateDefinition shape + layout reference -----------------------------

describe('TemplateDefinition', () => {
  it('accepts an inline-tree layout that reuses PatternTree', () => {
    const tpl: TemplateDefinition = {
      id: 't1',
      name: 'Landing Page',
      category: 'landing',
      layout: { kind: 'tree', tree: pageTree() },
      supportedPageTypes: ['page', 'landing'],
      version: '1.0.0',
    }
    expect(isTemplateTreeLayout(tpl.layout)).toBe(true)
    expect(isTemplatePatternsLayout(tpl.layout)).toBe(false)
    if (isTemplateTreeLayout(tpl.layout)) {
      expect(tpl.layout.tree.sections[0].type).toBe('section') // Pattern tree shape
    }
  })

  it('accepts a pattern-composition layout referenced by id', () => {
    const layout: TemplateLayoutRef = { kind: 'patterns', patternIds: ['hero.a', 'cta.b'] }
    const tpl: TemplateDefinition = { id: 't2', name: 'Composed', layout }
    expect(isTemplatePatternsLayout(tpl.layout)).toBe(true)
    if (isTemplatePatternsLayout(tpl.layout)) {
      expect(tpl.layout.patternIds).toEqual(['hero.a', 'cta.b'])
    }
  })

  it('the two layout guards are mutually exclusive', () => {
    const tree: TemplateLayoutRef = { kind: 'tree', tree: pageTree() }
    const patterns: TemplateLayoutRef = { kind: 'patterns', patternIds: [] }
    expect([isTemplateTreeLayout(tree), isTemplatePatternsLayout(tree)]).toEqual([true, false])
    expect([isTemplateTreeLayout(patterns), isTemplatePatternsLayout(patterns)]).toEqual([false, true])
  })
})

// ---- TemplateProvider is implementable + consistent (test-only stub) ---------

// A trivial in-memory implementation ONLY to prove the contract is consistent. This is
// NOT shipped runtime — no LocalTemplateProvider exists in this phase.
class StubTemplateProvider implements TemplateProvider {
  private map = new Map<string, TemplateDefinition>()
  all(): TemplateDefinition[] {
    return [...this.map.values()]
  }
  get(id: string): TemplateDefinition | undefined {
    return this.map.get(id)
  }
  register(template: TemplateDefinition): TemplateDefinition {
    this.map.set(template.id, template)
    return template
  }
  update(id: string, patch: Partial<Omit<TemplateDefinition, 'id'>>): TemplateDefinition | undefined {
    const current = this.map.get(id)
    if (!current) return undefined
    const updated = { ...current, ...patch, id }
    this.map.set(id, updated)
    return updated
  }
  remove(id: string): boolean {
    return this.map.delete(id)
  }
}

describe('TemplateProvider contract', () => {
  const tpl = (id: string, name: string): TemplateDefinition => ({
    id,
    name,
    layout: { kind: 'patterns', patternIds: [] },
  })

  it('supports register/get/all/update/remove consistently', () => {
    const provider: TemplateProvider = new StubTemplateProvider()
    provider.register(tpl('a', 'A'))
    provider.register(tpl('b', 'B'))
    expect(provider.all().map((t) => t.id)).toEqual(['a', 'b'])
    expect(provider.get('a')?.name).toBe('A')

    const updated = provider.update('a', { name: 'A2' })
    expect(updated?.id).toBe('a')
    expect(provider.get('a')?.name).toBe('A2')
    expect(provider.update('missing', { name: 'x' })).toBeUndefined()

    expect(provider.remove('a')).toBe(true)
    expect(provider.get('a')).toBeUndefined()
    expect(provider.remove('missing')).toBe(false)
  })
})

// ---- Pattern v1 contracts unchanged ------------------------------------------

describe('Pattern contracts remain unchanged', () => {
  it('PatternProvider still exposes its full surface (incl. duplicate)', () => {
    for (const method of ['all', 'get', 'register', 'update', 'duplicate', 'remove'] as const) {
      expect(typeof patternProvider[method]).toBe('function')
    }
  })

  it('createPatternDefinition still yields { id, name, tree }', () => {
    patternLibraryRegistry.clear()
    const def = createPatternDefinition({ name: 'P', sections: [{ type: 'section', data: {}, blocks: [] }] })
    expect(def.id).toMatch(/^pattern-/)
    expect(def.name).toBe('P')
    expect(def.tree.sections).toHaveLength(1)
  })
})
