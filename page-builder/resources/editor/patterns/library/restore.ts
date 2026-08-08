// Phase 26.11 — restore a pattern snapshot back into a concrete section tree.
//
// The default output is ID-FREE, exactly like a layout preset / `createComponentPayload`
// — the server assigns ids on `insert-pattern`, which is what guarantees no duplicate
// ids on replay. `regenerateIds` is an optional, defensive path for callers that need a
// standalone valid tree client-side (e.g. a preview): it stamps a session-unique id on
// every node, so a restored tree never carries duplicate ids either.
//
// Each restore deep-clones the snapshot, so an inserted tree never shares references
// with the stored definition (re-inserting the same pattern twice yields independent
// trees).

import type { BlockNode, SectionNode } from '../../types'
import type { PatternBlockSnapshot, PatternSectionSnapshot, PatternTree } from './patternDefinition'
import { uniqueId } from './ids'

export interface RestoreOptions {
  /** Stamp a fresh, session-unique id on every node (default: leave nodes id-free). */
  regenerateIds?: boolean
  /** Override the id factory (defaults to the built-in session-unique generator). */
  makeId?: () => string
}

/** A plain, JSON-safe deep clone. Snapshots are already plain, so `toRaw` is not needed. */
function clonePlain<T>(value: T): T {
  return JSON.parse(JSON.stringify(value ?? ({} as T))) as T
}

function restoreBlock(snapshot: PatternBlockSnapshot, makeId: (() => string) | null): BlockNode {
  const data = clonePlain(snapshot.data ?? {})
  if (Array.isArray(data.blocks)) {
    data.blocks = (data.blocks as PatternBlockSnapshot[]).map((child) => restoreBlock(child, makeId))
  }
  const node: BlockNode = { type: snapshot.type, data }
  if (makeId) node.id = makeId()
  return node
}

function restoreSection(snapshot: PatternSectionSnapshot, makeId: (() => string) | null): SectionNode {
  const node: SectionNode = {
    type: snapshot.type,
    data: clonePlain(snapshot.data ?? {}),
    blocks: (snapshot.blocks ?? []).map((child) => restoreBlock(child, makeId)),
  }
  if (makeId) node.id = makeId()
  return node
}

/** Rebuild a pattern tree into section nodes ready for the insert pipeline. */
export function restorePattern(tree: PatternTree, options: RestoreOptions = {}): SectionNode[] {
  const makeId = options.regenerateIds ? (options.makeId ?? (() => uniqueId('node'))) : null
  return (tree.sections ?? []).map((section) => restoreSection(section, makeId))
}
