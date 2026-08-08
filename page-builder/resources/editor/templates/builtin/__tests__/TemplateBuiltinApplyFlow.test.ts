// Phase 26.25 — a BUILT-IN template applies end-to-end through the unchanged runtime.
//
// Registers the company pack, then applies a built-in template both via the runtime binding
// (applyTemplateFromSource) and via the Phase 26.23 Apply UX intent with a builtin selection.
// One insert-pattern command, one undo — the same path as a user template.

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

import { useEditorStore } from '../../../stores/editor'
import { builtinTemplateDefinitionSource, applyTemplateFromSource } from '../../source'
import { builtinTemplateLibrary, type TemplateLibraryItem } from '../../library'
import { applyTemplateSelection } from '../../apply'
import { patternLibraryRegistry } from '../../../patterns/library'
import { BuiltinTemplatePackRegistry, registerBuiltinPacks } from '..'
import type { SectionNode, LayoutPayload } from '../../../types'

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

const heroItem: TemplateLibraryItem = {
  id: 'builtin:hero-basic',
  templateId: 'hero-basic',
  name: 'Basic Hero',
  category: 'hero',
  source: 'builtin',
}

beforeEach(() => {
  setActivePinia(createPinia())
  patternLibraryRegistry.clear()
  builtinTemplateDefinitionSource.clear()
  builtinTemplateLibrary.clear()
  registerBuiltinPacks({ registry: new BuiltinTemplatePackRegistry() })
})

describe('built-in apply flow', () => {
  it('applies a built-in template via the runtime binding (one command, one undo)', async () => {
    const { editor, runCommand } = seedEditor()
    const result = await applyTemplateFromSource('hero-basic', 'builtin', editor)
    expect(result.ok).toBe(true)
    expect(runCommand).toHaveBeenCalledTimes(1)
    expect(runCommand.mock.calls[0][1]).toBe('insert-pattern')
    expect(editor.undoStack).toHaveLength(1)
    expect(editor.currentPayload.sections).toHaveLength(1)
  })

  it('applies a built-in selection through the Apply UX intent (source-aware)', async () => {
    const { editor, runCommand } = seedEditor()
    const result = await applyTemplateSelection({ templateId: 'hero-basic', source: 'builtin', item: heroItem }, editor)
    expect(result.ok).toBe(true)
    expect(runCommand).toHaveBeenCalledTimes(1)
    expect(editor.undoStack).toHaveLength(1)
  })

  it('server re-keys the id-free built-in tree (client payload had no ids)', async () => {
    const { editor, runCommand } = seedEditor()
    await applyTemplateFromSource('cta-basic', 'builtin', editor)
    const sent = runCommand.mock.calls[0][2].sections as SectionNode[]
    expect(sent[0].id).toBeUndefined() // id-free client payload → server assigns ids
    expect(editor.selectedComponentId).toBe('srv-1')
  })
})
