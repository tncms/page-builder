// Phase 26.25 — built-in pack validation rules + component compatibility.

import { describe, it, expect } from 'vitest'
import { validatePack, collectTreeIssues, type BuiltinTemplatePack } from '..'
import { companyPack } from '../packs/company/companyPack'
import type { TemplateDefinition } from '../../contracts'
import type { PatternTree } from '../../../patterns/library'

function treeDef(id: string, tree: PatternTree, name = id): TemplateDefinition {
  return { id, name, layout: { kind: 'tree', tree } }
}
function pack(over: Partial<BuiltinTemplatePack['manifest']>, definitions: TemplateDefinition[]): BuiltinTemplatePack {
  return {
    manifest: { id: 'p', name: 'P', version: '1.0.0', templates: definitions.map((d) => d.id), ...over },
    definitions,
  }
}
const oneSection: PatternTree = { sections: [{ type: 'section', data: {}, blocks: [{ type: 'heading', data: {} }] }] }

describe('validatePack — manifest', () => {
  it('accepts the bundled company pack (permissive, no metadata)', () => {
    expect(validatePack(companyPack)).toEqual([])
  })

  it('flags a missing id, bad version, and unsupported schema', () => {
    expect(validatePack(pack({ id: '' }, [treeDef('a', oneSection)]))[0].code).toBe('invalid-manifest')
    expect(validatePack(pack({ version: '1.0' }, [treeDef('a', oneSection)])).some((i) => i.code === 'invalid-version')).toBe(true)
    expect(
      validatePack(pack({ schema: 'pagebuilder/99' }, [treeDef('a', oneSection)])).some((i) => i.code === 'unsupported-schema'),
    ).toBe(true)
  })

  it('flags a duplicate pack id via knownPackIds', () => {
    const issues = validatePack(pack({ id: 'company-pack' }, [treeDef('a', oneSection)]), {
      knownPackIds: new Set(['company-pack']),
    })
    expect(issues.some((i) => i.code === 'duplicate-pack')).toBe(true)
  })
})

describe('validatePack — reference integrity', () => {
  it('flags an orphan definition and a missing definition', () => {
    const orphan = pack({ templates: ['a'] }, [treeDef('a', oneSection), treeDef('b', oneSection)])
    expect(validatePack(orphan).some((i) => i.code === 'orphan-definition' && i.templateId === 'b')).toBe(true)

    const missing = pack({ templates: ['a', 'ghost'] }, [treeDef('a', oneSection)])
    expect(validatePack(missing).some((i) => i.code === 'missing-definition' && i.templateId === 'ghost')).toBe(true)
  })
})

describe('validatePack — definition + compatibility', () => {
  it('flags invalid nesting (container may only hold columns)', () => {
    const bad: PatternTree = {
      sections: [{ type: 'section', data: {}, blocks: [{ type: 'container', data: { blocks: [{ type: 'heading', data: {} }] } }] }],
    }
    const issues = validatePack(pack({}, [treeDef('a', bad)]))
    expect(issues.some((i) => i.code === 'invalid-nesting' && i.templateId === 'a')).toBe(true)
  })

  it('flags a non-tree layout for a built-in template', () => {
    const patterns: TemplateDefinition = { id: 'a', name: 'A', layout: { kind: 'patterns', patternIds: ['x'] } }
    const issues = validatePack(pack({}, [patterns]))
    expect(issues.some((i) => i.code === 'unsupported-layout')).toBe(true)
  })
})

describe('collectTreeIssues — metadata compatibility', () => {
  const metadata = { sections: [{ type: 'section' }], blocks: [{ type: 'heading' }, { type: 'text' }, { type: 'button' }, { type: 'container' }, { type: 'column' }] } as never

  it('passes the company pack against real metadata', () => {
    for (const def of companyPack.definitions) {
      if (def.layout.kind === 'tree') expect(collectTreeIssues(def.layout.tree, { metadata })).toEqual([])
    }
  })

  it('reports an unknown block when metadata lacks the type', () => {
    const tree: PatternTree = { sections: [{ type: 'section', data: {}, blocks: [{ type: 'mystery', data: {} }] }] }
    const issues = collectTreeIssues(tree, { metadata })
    expect(issues.some((i) => i.code === 'unknown-block')).toBe(true)
  })

  it('reports an empty tree', () => {
    expect(collectTreeIssues({ sections: [] }).some((i) => i.code === 'empty-tree')).toBe(true)
  })
})
