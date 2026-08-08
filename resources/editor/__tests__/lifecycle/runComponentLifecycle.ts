// Phase 26.3.16 — Reusable component lifecycle harness.
//
// Drives a single registered component through the *client* half of the canonical
// lifecycle (spec Part 3, stages 1–11, 18–19) against the REAL editor store and a
// faithful fake of the server insert-component command. It exists so we stop
// judging a component "works" by assumption: every stage returns concrete PASS/
// FAIL evidence, and the first failing stage is reported verbatim.
//
// What this harness CAN prove (deterministic, no browser/DB):
//   definition-exists, metadata-resolves, default-payload-shape, candidate-valid,
//   single-command, returned-node, unique-id, selected-after-insert, tree-hydration,
//   undo-removes, redo-restores.
// What it CANNOT prove (see the audit doc's NOT-TESTED column): real Blade/public
// rendering, DB save/reload, publish, and pixel-level editor render — those need
// the PHP suite or a browser and are tracked separately in the matrix.
//
// The fake command mirrors LayoutMutator's insert-component contract exactly: the
// server regenerates the id (the client never invents one), appends the node under
// target_parent_id at index using the CANONICAL child path, and selects it.
//
// Canonical child storage (confirmed against utils/layout.ts):
//   • a Section holds its children in `section.blocks[]`
//   • a Block (container/column) holds its children in `block.data.blocks[]`
// The two levels use different paths; the harness honors both.

import { createPinia, setActivePinia } from 'pinia'
import { useEditorStore } from '../../stores/editor'
import { findComponentById } from '../../utils/layout'
import { insertCandidates } from '../../utils/navigatorInsert'
import type { DefinitionMeta, EditorMetadata, LayoutPayload } from '../../types'
import metadataFixture from './component-metadata.json'

export type StageResult = 'PASS' | 'FAIL' | 'N/A'

export interface LifecycleReport {
  type: string
  kind: 'section' | 'block'
  target: string | null
  entryPoint: string
  stages: Record<string, StageResult>
  firstFailureStage: string | null
  insertedId: string | null
  notes: string[]
}

/** The real serialized editor metadata (generated from DefaultLibrary via tinker). */
export const METADATA = metadataFixture as unknown as EditorMetadata

const HOST_SECTION_ID = 'host-section'
const HOST_CONTAINER_ID = 'host-container'
const HOST_COLUMN_ID = 'host-column'

interface LifecycleOptions {
  type: string
  kind: 'section' | 'block'
  /** Parent id for block inserts; null (root) for sections. Auto-chosen if omitted. */
  target?: string | null
  entryPoint?: string
}

type PlainNode = {
  id: string
  type: string
  kind: 'section' | 'block'
  data: Record<string, unknown>
  blocks?: PlainNode[]
}

function clone<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T
}

/**
 * Find the RAW node reference by id (so mutations affect the payload). The store's
 * `findComponentById` returns a projection and `findComponentNode` a clone, so the
 * fake server needs its own in-place traversal over the canonical child paths.
 */
function findRawNode(payload: LayoutPayload, id: string): PlainNode | null {
  const p = payload as unknown as { sections: PlainNode[] }
  const inBlocks = (blocks: PlainNode[] | undefined): PlainNode | null => {
    for (const b of blocks ?? []) {
      if (b.id === id) return b
      const nested = (b.data?.blocks as PlainNode[] | undefined) ?? undefined
      const hit = inBlocks(nested)
      if (hit) return hit
    }
    return null
  }
  for (const s of p.sections) {
    if (s.id === id) return s
    const hit = inBlocks(s.blocks)
    if (hit) return hit
  }
  return null
}

/**
 * A host document: one Section → Container → Column chain, so every block kind has
 * a legal parent (content blocks → column, columns → container, containers →
 * section) without special-casing any single type.
 */
function seedPayload(): LayoutPayload {
  const payload = {
    sections: [
      {
        id: HOST_SECTION_ID,
        type: 'section',
        kind: 'section',
        data: {},
        blocks: [
          {
            id: HOST_CONTAINER_ID,
            type: 'container',
            kind: 'block',
            data: {
              blocks: [
                { id: HOST_COLUMN_ID, type: 'column', kind: 'block', data: { blocks: [] } },
              ],
            },
          },
        ],
      },
    ],
  }
  return payload as unknown as LayoutPayload
}

/** The legal default parent for a block kind, following the real nesting policy. */
function defaultTargetFor(type: string): string {
  if (type === 'container') return HOST_SECTION_ID
  if (type === 'column') return HOST_CONTAINER_ID
  return HOST_COLUMN_ID
}

let idCounter = 0
function serverId(type: string): string {
  idCounter += 1
  return `${type}-srv-${idCounter}`
}

/** Insert a node under `parentId` at `index` using the canonical child path. */
function serverInsert(
  payload: LayoutPayload,
  component: { type: string; data: Record<string, unknown>; blocks?: unknown[] },
  parentId: string | null,
  index: number,
): { payload: LayoutPayload; newId: string } {
  const next = clone(payload) as unknown as { sections: PlainNode[] }
  const newId = serverId(component.type)

  if (parentId === null) {
    next.sections.splice(index, 0, {
      id: newId,
      type: component.type,
      kind: 'section',
      data: clone(component.data ?? {}),
      blocks: [],
    })
    return { payload: next as unknown as LayoutPayload, newId }
  }

  const node: PlainNode = {
    id: newId,
    type: component.type,
    kind: 'block',
    // Layout blocks carry their `blocks` array inside `data` (from defaults).
    data: clone(component.data ?? {}),
  }

  // A Section parent stores children in `section.blocks`; a Block parent stores
  // them in `block.data.blocks`.
  const section = next.sections.find((s) => s.id === parentId)
  if (section) {
    section.blocks = section.blocks ?? []
    section.blocks.splice(index, 0, node)
    return { payload: next as unknown as LayoutPayload, newId }
  }

  const parent = findRawNode(next as unknown as LayoutPayload, parentId)
  if (!parent) throw new Error(`server: parent ${parentId} not found`)
  parent.data = parent.data ?? {}
  const childBlocks = (parent.data.blocks as PlainNode[] | undefined) ?? []
  childBlocks.splice(index, 0, node)
  parent.data.blocks = childBlocks
  return { payload: next as unknown as LayoutPayload, newId }
}

/**
 * Run the client lifecycle for one component. Never throws for a failing stage —
 * it records FAIL and stops at the first, so a broken component still yields a
 * full, comparable report.
 */
export async function runComponentLifecycle(options: LifecycleOptions): Promise<LifecycleReport> {
  const { type, kind } = options
  const entryPoint = options.entryPoint ?? 'insertComponent'
  const target =
    options.target !== undefined ? options.target : kind === 'section' ? null : defaultTargetFor(type)

  setActivePinia(createPinia())
  const store = useEditorStore()

  const stages: Record<string, StageResult> = {}
  const notes: string[] = []
  let firstFailureStage: string | null = null
  let insertedId: string | null = null

  const fail = (stage: string, note?: string): void => {
    stages[stage] = 'FAIL'
    if (!firstFailureStage) firstFailureStage = stage
    if (note) notes.push(`${stage}: ${note}`)
  }
  const pass = (stage: string): void => {
    stages[stage] = 'PASS'
  }

  // Stage 1 — definition exists in the registry snapshot.
  const registry: DefinitionMeta[] = kind === 'section' ? METADATA.sections : METADATA.blocks
  const definition = registry.find((d) => d.type === type) ?? null
  if (!definition) {
    fail('definition_exists', `no ${kind} definition for "${type}"`)
    return { type, kind, target, entryPoint, stages, firstFailureStage, insertedId, notes }
  }
  pass('definition_exists')

  // Stage 3 — metadata resolves (label present).
  if (!definition.label) fail('metadata_resolves', 'missing label')
  else pass('metadata_resolves')

  // Stage 6 — default payload has an OBJECT shape (not a list / null / missing).
  const defaults = definition.defaults as unknown
  const isObjectShape = defaults !== null && typeof defaults === 'object' && !Array.isArray(defaults)
  if (!isObjectShape) {
    fail('default_payload_shape', `defaults is ${Array.isArray(defaults) ? 'a list' : typeof defaults}`)
  } else pass('default_payload_shape')

  store.metadata = METADATA
  store.currentPayload = seedPayload()
  store.undoStack = []
  store.redoStack = []

  store.client = {
    async runCommand(_before: LayoutPayload, name: string, params: Record<string, unknown>) {
      if (name !== 'insert-component') throw new Error(`unexpected command ${name}`)
      const component = params.component as { type: string; data: Record<string, unknown>; blocks?: unknown[] }
      const parentId = (params.target_parent_id as string | null) ?? null
      const index = (params.index as number) ?? 0
      const { payload, newId } = serverInsert(store.currentPayload as LayoutPayload, component, parentId, index)
      return { payload, selection: { selected: newId } }
    },
  } as unknown as typeof store.client

  // Stage 7 — candidate/nesting validation: the target must accept this type.
  const parentType = target === null ? null : findComponentById(store.currentPayload, target)?.type ?? null
  const candidates = insertCandidates(store.metadata, parentType)
  const candidateOk = candidates.some((d) => d.type === type)
  if (!candidateOk) fail('candidate_valid', `"${type}" not a candidate under ${parentType ?? 'root'}`)
  else pass('candidate_valid')

  // Stages 8–11 — single command, returned node, unique id, selection, hydration.
  const before = clone(store.currentPayload as LayoutPayload)
  const beforeUndo = store.undoStack.length
  insertedId = await store.insertComponent({ parentId: target, componentType: type })

  if (!insertedId) {
    fail('command_commit', store.commandError ?? 'insertComponent returned null')
    return { type, kind, target, entryPoint, stages, firstFailureStage, insertedId, notes }
  }
  pass('command_commit')

  if (store.undoStack.length !== beforeUndo + 1) fail('single_command', `undo delta ${store.undoStack.length - beforeUndo}`)
  else pass('single_command')

  const node = findComponentById(store.currentPayload, insertedId)
  if (!node) fail('returned_node', 'inserted id not in tree')
  else pass('returned_node')

  if (findComponentById(before, insertedId)) fail('unique_id', 'id already existed pre-insert')
  else pass('unique_id')

  if (store.selectedComponentId !== insertedId) fail('selected_after_insert', `selected=${store.selectedComponentId}`)
  else pass('selected_after_insert')

  // Tree hydration under the expected parent (canonical path per level).
  const hydrated = isChildOf(store.currentPayload, target, insertedId)
  if (!hydrated) fail('tree_hydration', 'node not under expected parent')
  else pass('tree_hydration')

  // Stage 18 — undo removes it.
  store.undo()
  if (findComponentById(store.currentPayload, insertedId)) fail('undo', 'node still present after undo')
  else pass('undo')

  // Stage 19 — redo restores it.
  store.redo()
  if (!findComponentById(store.currentPayload, insertedId)) fail('redo', 'node missing after redo')
  else pass('redo')

  return { type, kind, target, entryPoint, stages, firstFailureStage, insertedId, notes }
}

/** Whether `childId` is a direct child of `parentId` (root sections when null). */
function isChildOf(payload: LayoutPayload, parentId: string | null, childId: string): boolean {
  const p = payload as unknown as { sections: PlainNode[] }
  if (parentId === null) return p.sections.some((s) => s.id === childId)
  const section = p.sections.find((s) => s.id === parentId)
  if (section) return (section.blocks ?? []).some((b) => b.id === childId)
  const parent = findRawNode(payload, parentId)
  const childBlocks = (parent?.data?.blocks as PlainNode[] | undefined) ?? []
  return childBlocks.some((b) => b.id === childId)
}
