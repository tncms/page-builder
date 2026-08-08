// Phase 26.26 — import: validation-first, atomic, into the existing imported source + library,
// then resolve + apply through the unchanged runtime.

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

import { useEditorStore } from '../../../stores/editor'
import { importedTemplateDefinitionSource, resolveTemplateDefinition, applyTemplateFromSource } from '../../source'
import { importedTemplateLibrary } from '../../library'
import { patternLibraryRegistry, type PatternTree } from '../../../patterns/library'
import type { TemplateDefinition } from '../../contracts'
import type { SectionNode, LayoutPayload } from '../../../types'
import {
  exportTemplatePackage,
  validateTemplatePackage,
  importTemplatePackage,
  TemplatePackageError,
  type TemplatePackage,
} from '..'

const validTree: PatternTree = { sections: [{ type: 'section', data: {}, blocks: [{ type: 'heading', data: { text: 'Hi' } }] }] }
function def(id = 'imp-hero', name = 'Imported Hero'): TemplateDefinition {
  return { id, name, category: 'hero', layout: { kind: 'tree', tree: validTree } }
}
function pkg(defs: TemplateDefinition[] = [def()]): TemplatePackage {
  return exportTemplatePackage(defs, { id: 'kit', name: 'Kit' })
}

function seedEditor() {
  const editor = useEditorStore()
  editor.currentPayload = { schema: 'pagebuilder/01', sections: [] } as LayoutPayload
  let n = 0
  const rekey = (node: SectionNode): SectionNode => {
    const id = `srv-${(n += 1)}`
    const data = { ...node.data }
    if (Array.isArray(data.blocks)) data.blocks = (data.blocks as SectionNode[]).map(rekey)
    return { ...node, id, data, ...(node.blocks ? { blocks: node.blocks.map(rekey) } : {}) }
  }
  const runCommand = vi.fn(async (before: LayoutPayload, command: string, params: Record<string, unknown>) => {
    if (command !== 'insert-pattern') return { payload: before, selection: { selected: null } }
    const added = (params.sections as SectionNode[]).map(rekey)
    return { payload: { ...before, sections: [...before.sections, ...added] }, selection: { selected: added[0].id } }
  })
  ;(editor as unknown as { client: unknown }).client = { runCommand }
  return { editor, runCommand }
}

beforeEach(() => {
  setActivePinia(createPinia())
  patternLibraryRegistry.clear()
  importedTemplateDefinitionSource.clear()
  importedTemplateLibrary.clear()
})

// ---- validation -------------------------------------------------------------

describe('validateTemplatePackage', () => {
  it('accepts a well-formed package', () => {
    expect(validateTemplatePackage(pkg())).toEqual([])
  })

  it('rejects bad schema / version / metadata / empty', () => {
    expect(validateTemplatePackage({ ...pkg(), schema: 'nope' }).some((i) => i.code === 'unsupported-schema')).toBe(true)
    expect(validateTemplatePackage({ ...pkg(), version: '9.9' }).some((i) => i.code === 'unsupported-version')).toBe(true)
    expect(validateTemplatePackage({ ...pkg(), metadata: { id: '', name: '' } }).some((i) => i.code === 'invalid-metadata')).toBe(true)
    expect(validateTemplatePackage({ ...pkg(), templates: [] }).some((i) => i.code === 'empty-package')).toBe(true)
  })

  it('rejects duplicate template ids and incompatible nesting', () => {
    expect(validateTemplatePackage(pkg([def('a'), def('a')])).some((i) => i.code === 'duplicate-template')).toBe(true)
    const badNest: PatternTree = { sections: [{ type: 'section', data: {}, blocks: [{ type: 'container', data: { blocks: [{ type: 'heading', data: {} }] } }] }] }
    const bad = pkg([{ id: 'x', name: 'X', layout: { kind: 'tree', tree: badNest } }])
    expect(validateTemplatePackage(bad).some((i) => i.code === 'invalid-nesting')).toBe(true)
  })

  it('enforces the asset boundary (no storage ids, safe thumbnail)', () => {
    const withMediaId = pkg([{ id: 'm', name: 'M', layout: { kind: 'tree', tree: { sections: [{ type: 'section', data: { image: { media_id: 42 } }, blocks: [] }] } } }])
    expect(validateTemplatePackage(withMediaId).some((i) => i.code === 'storage-identity')).toBe(true)
    const badThumb = pkg([{ ...def(), preview: 'javascript:alert(1)' }])
    expect(validateTemplatePackage(badThumb).some((i) => i.code === 'unsafe-thumbnail')).toBe(true)
  })
})

// ---- import -----------------------------------------------------------------

describe('importTemplatePackage', () => {
  it('lands templates in the imported source + library, resolvable via the source resolver', () => {
    const result = importTemplatePackage(pkg())
    expect(result).toMatchObject({ ok: true, packageId: 'kit', templateIds: ['imp-hero'] })
    expect(resolveTemplateDefinition('imp-hero', 'imported').name).toBe('Imported Hero')
    expect(importedTemplateLibrary.all().map((i) => i.id)).toEqual(['imported:imp-hero'])
  })

  it('applies an imported template end-to-end (one command, one undo)', async () => {
    const { editor, runCommand } = seedEditor()
    importTemplatePackage(pkg())
    const applied = await applyTemplateFromSource('imp-hero', 'imported', editor)
    expect(applied.ok).toBe(true)
    expect(runCommand).toHaveBeenCalledTimes(1)
    expect(editor.undoStack).toHaveLength(1)
  })

  it('is atomic on an invalid package — throws, registers nothing', () => {
    expect(() => importTemplatePackage({ ...pkg(), schema: 'nope' })).toThrow(TemplatePackageError)
    expect(importedTemplateDefinitionSource.getDefinition('imp-hero')).toBeNull()
    expect(importedTemplateLibrary.all()).toHaveLength(0)
  })

  it('rejects a re-import of an already-imported id — atomic', () => {
    importTemplatePackage(pkg())
    let error: unknown
    try {
      importTemplatePackage(pkg([def('imp-hero'), def('fresh')]))
    } catch (e) {
      error = e
    }
    expect(error).toBeInstanceOf(TemplatePackageError)
    expect((error as TemplatePackageError).issues[0].code).toBe('duplicate-registration')
    // 'fresh' must NOT have been registered (all-or-nothing).
    expect(importedTemplateDefinitionSource.getDefinition('fresh')).toBeNull()
  })
})
