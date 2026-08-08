// Phase 26.26 — export: portable, id-free, JSON-safe; URLs and portable data preserved.

import { describe, it, expect } from 'vitest'
import {
  exportTemplatePackage,
  serializeTemplatePackage,
  parseTemplatePackage,
  normalizeDefinition,
  stripTreeIds,
  TEMPLATE_PACKAGE_SCHEMA,
  TEMPLATE_PACKAGE_VERSION,
} from '..'
import type { TemplateDefinition } from '../../contracts'
import type { PatternTree } from '../../../patterns/library'

const treeWithIds: PatternTree = {
  sections: [
    {
      type: 'section',
      id: 'sec-1',
      data: { padding: { top: 10 }, image: { src: 'https://cdn.example.com/a.webp' } },
      blocks: [
        { type: 'container', id: 'c-1', data: { blocks: [{ type: 'column', id: 'col-1', data: { blocks: [{ type: 'text', id: 't-1', data: { text: 'hi', color: '{{color.primary}}' } }] } }] } },
      ],
    },
  ] as never,
}
function def(over: Partial<TemplateDefinition> = {}): TemplateDefinition {
  return { id: 'hero', name: 'Hero', preview: 'https://cdn.example.com/thumb.webp', layout: { kind: 'tree', tree: treeWithIds }, ...over }
}

describe('exportTemplatePackage', () => {
  it('wraps definitions in a schema + version envelope', () => {
    const pkg = exportTemplatePackage([def()], { id: 'kit', name: 'Starter Kit' })
    expect(pkg.schema).toBe(TEMPLATE_PACKAGE_SCHEMA)
    expect(pkg.version).toBe(TEMPLATE_PACKAGE_VERSION)
    expect(pkg.metadata).toEqual({ id: 'kit', name: 'Starter Kit' })
    expect(pkg.templates).toHaveLength(1)
  })

  it('strips component ids but preserves template identity, tokens, and asset URLs', () => {
    const pkg = exportTemplatePackage([def()], { id: 'kit', name: 'Kit' })
    const t = pkg.templates[0]
    expect(t.id).toBe('hero') // template identity kept
    const tree = (t.layout as { tree: PatternTree }).tree
    const section = tree.sections[0] as Record<string, unknown>
    expect(section.id).toBeUndefined() // component ids stripped
    const container = (section.blocks as Record<string, unknown>[])[0]
    expect(container.id).toBeUndefined()
    // Portable data survives verbatim.
    expect((section.data as Record<string, unknown>).image).toEqual({ src: 'https://cdn.example.com/a.webp' })
    const column = ((container.data as Record<string, unknown>).blocks as Record<string, unknown>[])[0]
    const text = ((column.data as Record<string, unknown>).blocks as Record<string, unknown>[])[0]
    expect((text.data as Record<string, unknown>).color).toBe('{{color.primary}}') // token ref preserved
  })

  it('round-trips through serialize → parse', () => {
    const pkg = exportTemplatePackage([def()], { id: 'kit', name: 'Kit' })
    const restored = parseTemplatePackage(serializeTemplatePackage(pkg))
    expect(restored).toEqual(pkg)
  })

  it('parse throws on malformed JSON', () => {
    expect(() => parseTemplatePackage('{not json')).toThrow(expect.objectContaining({ name: 'TemplatePackageError' }))
  })
})

describe('normalizeDefinition / stripTreeIds', () => {
  it('is idempotent and leaves an already id-free tree unchanged in shape', () => {
    const stripped = stripTreeIds(treeWithIds)
    expect(JSON.stringify(stripTreeIds(stripped))).toBe(JSON.stringify(stripped))
    expect(normalizeDefinition(def()).layout.kind).toBe('tree')
  })
})
