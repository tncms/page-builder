// Phase 26.24 — Template source definition resolution + runtime binding.
//
// Verifies the per-source definition stores, the cross-source resolver (explicit
// unsupported_template_source / template_definition_not_found), and applyTemplateFromSource —
// crucially that a BUILT-IN template now applies end-to-end through the unchanged runtime (one
// insert-pattern command, one undo). Regression: the runtime apply entries are untouched.

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

import { useEditorStore } from '../../../stores/editor'
import { localTemplateProvider } from '../../provider'
import { applyTemplateById, TemplateRuntimeError } from '../../runtime'
import { createPatternDefinition, patternLibraryRegistry, type PatternTree } from '../../../patterns/library'
import type { TemplateDefinition } from '../../contracts'
import type { SectionNode, LayoutPayload } from '../../../types'

import {
  StaticTemplateDefinitionSource,
  BuiltinTemplateDefinitionSource,
  UserTemplateDefinitionSource,
  ImportedTemplateDefinitionSource,
  TemplateSourceResolver,
  resolveTemplateDefinition,
  applyTemplateFromSource,
  builtinTemplateDefinitionSource,
  importedTemplateDefinitionSource,
} from '..'

function section(type = 'section'): SectionNode {
  return { type, data: { background: '#fff' }, blocks: [{ type: 'heading', data: {} }] }
}
function pageTree(): PatternTree {
  return createPatternDefinition({ name: 'x', sections: [section()] }).tree
}
function def(id: string, name = id): TemplateDefinition {
  return { id, name, layout: { kind: 'tree', tree: pageTree() } }
}

// Faithful insert-pattern fake (mirrors the runtime apply test): re-keys + selects new root.
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
  localTemplateProvider.clear()
  builtinTemplateDefinitionSource.clear()
  importedTemplateDefinitionSource.clear()
})

// ---- per-source stores ------------------------------------------------------

describe('StaticTemplateDefinitionSource', () => {
  it('registers, returns immutable clones, and reports null for a miss', () => {
    const src = new StaticTemplateDefinitionSource('builtin')
    src.register(def('a', 'A'))
    const got = src.getDefinition('a')!
    expect(got.name).toBe('A')
    got.name = 'MUT'
    expect(src.getDefinition('a')!.name).toBe('A') // stored copy untouched
    expect(src.getDefinition('missing')).toBeNull()
  })

  it('rejects a duplicate id and supports remove/clear', () => {
    const src = new StaticTemplateDefinitionSource('builtin')
    src.register(def('a'))
    expect(() => src.register(def('a'))).toThrow(TemplateRuntimeError)
    expect(src.remove('a')).toBe(true)
    expect(src.getDefinition('a')).toBeNull()
  })

  it('builtin and imported carry the right source keys, empty by default', () => {
    expect(new BuiltinTemplateDefinitionSource().source).toBe('builtin')
    expect(new ImportedTemplateDefinitionSource().source).toBe('imported')
    expect(new ImportedTemplateDefinitionSource().getDefinition('anything')).toBeNull()
  })
})

describe('UserTemplateDefinitionSource', () => {
  it('delegates to the runtime TemplateProvider (no duplicate storage)', () => {
    const src = new UserTemplateDefinitionSource(localTemplateProvider)
    expect(src.getDefinition('u1')).toBeNull()
    localTemplateProvider.register(def('u1', 'User'))
    expect(src.getDefinition('u1')!.name).toBe('User')
  })
})

// ---- resolver ---------------------------------------------------------------

describe('TemplateSourceResolver', () => {
  it('dispatches to the matching source and lists known sources', () => {
    const builtin = new BuiltinTemplateDefinitionSource()
    builtin.register(def('b1', 'Builtin'))
    const resolver = new TemplateSourceResolver([builtin, new UserTemplateDefinitionSource(localTemplateProvider)])
    expect(resolver.knownSources()).toEqual(['builtin', 'user'])
    expect(resolver.getDefinition('b1', 'builtin')!.name).toBe('Builtin')
    expect(resolver.getDefinition('nope', 'builtin')).toBeNull() // known source, no such template
  })

  it('throws unsupported_template_source for an unknown source KEY', () => {
    const resolver = new TemplateSourceResolver([new BuiltinTemplateDefinitionSource()])
    expect(() => resolver.getDefinition('x', 'marketplace')).toThrow(
      expect.objectContaining({ code: 'unsupported_template_source' }),
    )
  })
})

describe('resolveTemplateDefinition', () => {
  it('returns a known definition (default resolver)', () => {
    builtinTemplateDefinitionSource.register(def('agency-home', 'Agency'))
    expect(resolveTemplateDefinition('agency-home', 'builtin').name).toBe('Agency')
  })

  it('throws template_definition_not_found for a known source with no such template', () => {
    expect(() => resolveTemplateDefinition('ghost', 'builtin')).toThrow(
      expect.objectContaining({ code: 'template_definition_not_found' }),
    )
  })

  it('throws unsupported_template_source for an unknown source', () => {
    expect(() => resolveTemplateDefinition('x', 'cloud')).toThrow(
      expect.objectContaining({ code: 'unsupported_template_source' }),
    )
  })
})

// ---- runtime binding --------------------------------------------------------

describe('applyTemplateFromSource', () => {
  it('applies a BUILT-IN template end-to-end (one insert-pattern command, one undo)', async () => {
    const { editor, runCommand } = seedEditor()
    builtinTemplateDefinitionSource.register(def('agency-home', 'Agency Home'))

    const result = await applyTemplateFromSource('agency-home', 'builtin', editor)
    expect(result.ok).toBe(true)
    expect(runCommand).toHaveBeenCalledTimes(1)
    expect(runCommand.mock.calls[0][1]).toBe('insert-pattern')
    expect(editor.undoStack).toHaveLength(1)
    expect(editor.currentPayload.sections).toHaveLength(1)
  })

  it('applies a USER template through the same path', async () => {
    const { editor, runCommand } = seedEditor()
    localTemplateProvider.register(def('u1', 'User Hero'))
    const result = await applyTemplateFromSource('u1', 'user', editor)
    expect(result.ok).toBe(true)
    expect(runCommand).toHaveBeenCalledTimes(1)
  })

  it('is atomic on a missing definition — no command, no mutation', async () => {
    const { editor, runCommand } = seedEditor()
    const before = JSON.stringify(editor.currentPayload)
    await expect(applyTemplateFromSource('ghost', 'builtin', editor)).rejects.toMatchObject({
      code: 'template_definition_not_found',
    })
    expect(runCommand).not.toHaveBeenCalled()
    expect(editor.undoStack).toHaveLength(0)
    expect(JSON.stringify(editor.currentPayload)).toBe(before)
  })

  it('is atomic on an unknown source — no command', async () => {
    const { editor, runCommand } = seedEditor()
    await expect(applyTemplateFromSource('x', 'marketplace', editor)).rejects.toMatchObject({
      code: 'unsupported_template_source',
    })
    expect(runCommand).not.toHaveBeenCalled()
  })
})

// ---- regression: runtime apply entries unchanged ----------------------------

describe('regression — runtime apply entries unchanged', () => {
  it('applyTemplateById still resolves via its TemplateProvider and throws template_not_found', async () => {
    const { editor, runCommand } = seedEditor()
    await expect(applyTemplateById('nope', editor)).rejects.toMatchObject({ code: 'template_not_found' })
    expect(runCommand).not.toHaveBeenCalled()
  })
})
