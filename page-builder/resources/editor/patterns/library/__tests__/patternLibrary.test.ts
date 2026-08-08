// Phase 26.11 — Pattern Library Foundation.
//
// Covers serialization (structure/props/tokens/responsive/state values preserved, ids
// stripped), restore (valid tree, id regeneration with no duplicates, clone
// independence), the registry, and insertion through the EXISTING `insert-pattern`
// command (one undo, server re-key, selection = new root).

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

import {
  serializePattern,
  serializeSection,
  restorePattern,
  createPatternDefinition,
  PatternLibraryRegistry,
  insertPattern,
  PATTERN_LIBRARY_CATEGORIES,
  type PatternInsertTarget,
} from '../index'
import type { SectionNode, LayoutPayload } from '../../../types'
import { useEditorStore } from '../../../stores/editor'

// A section carrying every capability that must survive: a normal value, a token ref, a
// responsive envelope, a style-state map, and a nested container → column → button tree.
function richSection(): SectionNode {
  return {
    id: 'sec-1',
    type: 'section',
    data: {
      background: '#ffffff',
      padding: { desktop: '{{spacing.lg}}', mobile: '{{spacing.sm}}' }, // responsive + token
      states: { hover: { background: '{{color.primary}}' } }, // 26.10 style-state map
      blocks: undefined, // sections keep children on the node, not in data
    },
    blocks: [
      {
        id: 'ctr-1',
        type: 'container',
        data: {
          layout: 'flex',
          blocks: [
            {
              id: 'col-1',
              type: 'column',
              data: {
                width: '50',
                blocks: [
                  { id: 'btn-1', type: 'button', data: { label: 'Go', color: '{{color.text}}' } },
                ],
              },
            },
          ],
        },
      },
    ],
  }
}

// ---- Serialization -----------------------------------------------------------

describe('serializePattern', () => {
  it('preserves structure, hierarchy and children across all levels', () => {
    const tree = serializePattern([richSection()])
    expect(tree.sections).toHaveLength(1)
    const section = tree.sections[0]
    expect(section.type).toBe('section')
    const container = section.blocks[0]
    expect(container.type).toBe('container')
    const column = (container.data.blocks as { type: string }[])[0]
    expect(column.type).toBe('column')
    const button = ((column as { data: { blocks: { type: string }[] } }).data.blocks)[0]
    expect(button.type).toBe('button')
  })

  it('preserves normal values, token references, responsive envelopes and state maps', () => {
    const section = serializeSection(richSection())
    expect(section.data.background).toBe('#ffffff') // normal value
    expect(section.data.padding).toEqual({ desktop: '{{spacing.lg}}', mobile: '{{spacing.sm}}' }) // responsive + token
    expect(section.data.states).toEqual({ hover: { background: '{{color.primary}}' } }) // 26.10 state map
    const button = (((section.blocks[0].data.blocks as SectionNode['blocks'])[0].data.blocks) as SectionNode['blocks'])[0]
    expect(button.data.color).toBe('{{color.text}}') // deep token ref survives
  })

  it('strips ids at every level (the snapshot is id-free)', () => {
    const section = serializeSection(richSection())
    expect('id' in section).toBe(false)
    const container = section.blocks[0]
    expect('id' in container).toBe(false)
    const column = (container.data.blocks as SectionNode['blocks'])[0]
    expect('id' in column).toBe(false)
    const button = (column.data.blocks as SectionNode['blocks'])[0]
    expect('id' in button).toBe(false)
  })

  it('does not mutate the source payload', () => {
    const source = richSection()
    serializeSection(source)
    expect(source.id).toBe('sec-1')
    expect((source.blocks[0].data.blocks as SectionNode['blocks'])[0].id).toBe('col-1')
  })
})

// ---- Restore -----------------------------------------------------------------

describe('restorePattern', () => {
  it('rebuilds a valid section tree with values intact', () => {
    const tree = serializePattern([richSection()])
    const [section] = restorePattern(tree)
    expect(section.type).toBe('section')
    expect(section.data.padding).toEqual({ desktop: '{{spacing.lg}}', mobile: '{{spacing.sm}}' })
    expect(section.blocks[0].type).toBe('container')
  })

  it('is id-free by default (server assigns ids on insert)', () => {
    const [section] = restorePattern(serializePattern([richSection()]))
    expect(section.id).toBeUndefined()
    expect(section.blocks[0].id).toBeUndefined()
  })

  it('regenerates a fresh, unique id on every node with no duplicates', () => {
    const [section] = restorePattern(serializePattern([richSection()]), { regenerateIds: true })
    const ids: string[] = []
    const walk = (node: { id?: string; data: Record<string, unknown>; blocks?: unknown[] }) => {
      expect(typeof node.id).toBe('string')
      ids.push(node.id!)
      for (const child of (node.blocks as typeof node[]) ?? []) walk(child)
      for (const child of (node.data.blocks as typeof node[]) ?? []) walk(child)
    }
    walk(section)
    expect(ids).toHaveLength(4) // section + container + column + button
    expect(new Set(ids).size).toBe(4) // all unique
  })

  it('produces independent trees (re-restoring shares no references)', () => {
    const tree = serializePattern([richSection()])
    const a = restorePattern(tree)
    const b = restorePattern(tree)
    a[0].data.background = '#000000'
    expect(b[0].data.background).toBe('#ffffff') // untouched
  })
})

// ---- Registry ----------------------------------------------------------------

describe('PatternLibraryRegistry', () => {
  it('registers, gets, lists and filters by category', () => {
    const reg = new PatternLibraryRegistry()
    const hero = createPatternDefinition({ name: 'Hero A', category: 'hero', sections: [richSection()] })
    const cta = createPatternDefinition({ name: 'CTA A', category: 'cta', sections: [richSection()] })
    reg.register(hero)
    reg.register(cta)
    expect(reg.has(hero.id)).toBe(true)
    expect(reg.get(hero.id)?.name).toBe('Hero A')
    expect(reg.all()).toHaveLength(2)
    expect(reg.byCategory('hero')).toEqual([hero])
  })

  it('replaces by id, removes and clears', () => {
    const reg = new PatternLibraryRegistry()
    const p = createPatternDefinition({ id: 'p1', name: 'One', sections: [richSection()] })
    reg.register(p)
    reg.register({ ...p, name: 'One (edited)' })
    expect(reg.all()).toHaveLength(1)
    expect(reg.get('p1')?.name).toBe('One (edited)')
    expect(reg.remove('p1')).toBe(true)
    expect(reg.all()).toHaveLength(0)
    reg.register(p)
    reg.clear()
    expect(reg.all()).toHaveLength(0)
  })

  it('createPatternDefinition captures an id-free tree and an auto id', () => {
    const def = createPatternDefinition({ name: 'Captured', sections: [richSection()] })
    expect(def.id).toMatch(/^pattern-/)
    expect(def.tree.sections[0].type).toBe('section')
    expect('id' in def.tree.sections[0]).toBe(false)
  })

  it('exposes the foundation category vocabulary', () => {
    expect(PATTERN_LIBRARY_CATEGORIES).toEqual(['hero', 'content', 'cta', 'gallery', 'layout'])
  })
})

// ---- Insertion (existing command pipeline) -----------------------------------

describe('insertPattern', () => {
  it('dispatches exactly one insert-pattern command with the restored sections', async () => {
    const def = createPatternDefinition({ name: 'P', sections: [richSection()] })
    const executeCommand = vi.fn(async () => true)
    const target: PatternInsertTarget = { executeCommand }

    const ok = await insertPattern(def, target)
    expect(ok).toBe(true)
    expect(executeCommand).toHaveBeenCalledTimes(1)
    const [command, params] = executeCommand.mock.calls[0]
    expect(command).toBe('insert-pattern')
    const sections = (params as { sections: SectionNode[] }).sections
    expect(sections[0].type).toBe('section')
    expect(sections[0].id).toBeUndefined() // id-free → server re-keys
  })

  it('does not dispatch for an empty tree', async () => {
    const def = createPatternDefinition({ name: 'Empty', sections: [] })
    const executeCommand = vi.fn(async () => true)
    expect(await insertPattern(def, { executeCommand })).toBe(false)
    expect(executeCommand).not.toHaveBeenCalled()
  })
})

// ---- Insertion integration: one undo, server re-key, selection = new root ----

describe('insertPattern — through the real editor store', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('inserts via one command, re-keys ids server-side, selects the new root', async () => {
    const store = useEditorStore()
    store.currentPayload = { schema: 'pagebuilder/01', sections: [] } as LayoutPayload

    // Faithful `insert-pattern` fake: assign server ids recursively, select new root.
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
      const payload = { ...before, sections: [...before.sections, ...added] }
      return { payload, selection: { selected: added[0].id } }
    })
    ;(store as unknown as { client: unknown }).client = { runCommand }

    const def = createPatternDefinition({ name: 'P', sections: [richSection()] })
    const ok = await insertPattern(def, store)

    expect(ok).toBe(true)
    expect(runCommand).toHaveBeenCalledTimes(1)
    expect(runCommand.mock.calls[0][1]).toBe('insert-pattern')
    expect(store.undoStack).toHaveLength(1) // one undo entry
    expect(store.currentPayload.sections).toHaveLength(1)
    expect(store.selectedComponentId).toBe('srv-1') // new root selected

    // Server re-keyed every node — collect ids and assert uniqueness.
    const ids: string[] = []
    const collect = (node: SectionNode) => {
      if (node.id) ids.push(node.id)
      for (const b of (node.data.blocks as SectionNode[]) ?? []) collect(b)
      for (const b of node.blocks ?? []) collect(b)
    }
    store.currentPayload.sections.forEach(collect)
    expect(ids.length).toBeGreaterThanOrEqual(4)
    expect(new Set(ids).size).toBe(ids.length) // no duplicate component ids
  })
})
