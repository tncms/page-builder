import type { ComponentNode, DefinitionMeta, EditorMetadata } from '../types'
import { canContain } from './nestingPolicy'
import { canContainChildren } from './navigatorDescriptor'

/**
 * Navigator structural-insertion model (Phase 26.3, Increment 6).
 *
 * Pure, deterministic helpers that decide WHERE an Add Inside / Add Before / Add
 * After lands and WHICH registered components may go there. They invent nothing:
 * placement comes from the node's own position, the candidate set comes straight
 * from editor metadata filtered by the central {@link canContain} NestingPolicy
 * (the same rule the server enforces), and "can hold children" reuses the
 * Navigator descriptor's {@link canContainChildren}. No global state, no clock,
 * no randomness — so the whole insertion decision is unit-testable without a
 * store or a mounted component, and the actual mutation stays in the store's
 * single `insertComponent` command path (server remains authoritative).
 */

export type InsertPlacement = 'inside' | 'before' | 'after'

/** A node's slot among its siblings (subset of `siblingPosition`'s result). */
export interface SiblingSlot {
  parentId: string | null
  index: number
}

export interface InsertTarget {
  /** The parent the new component is inserted under (null = document root). */
  parentId: string | null
  /** The parent's component type, or null at the root (only sections live there). */
  parentType: string | null
  /** Insertion index within the parent, or null to append at the end. */
  index: number | null
}

/**
 * Resolve where a placement lands for `node`:
 *  - `inside`  → into the node itself, appended (index null);
 *  - `before`  → a sibling in the node's parent at the node's own index;
 *  - `after`   → a sibling in the node's parent just past it (index + 1).
 *
 * `slot` is the node's sibling position (from `siblingPosition`) and `parentType`
 * its parent's component type (null at the root) — both only used for the
 * relative placements. Returns null when a relative placement has no known slot.
 */
export function resolveInsertTarget(
  node: ComponentNode,
  placement: InsertPlacement,
  slot: SiblingSlot | null,
  parentType: string | null,
): InsertTarget | null {
  if (placement === 'inside') {
    return { parentId: node.id, parentType: node.type, index: null }
  }
  if (!slot) return null
  return {
    parentId: slot.parentId,
    parentType,
    index: placement === 'before' ? slot.index : slot.index + 1,
  }
}

/**
 * The registered definitions that may be inserted under a parent context, driven
 * entirely by editor metadata and the central NestingPolicy — never a duplicated
 * component list. The document root (`parentType === null`) accepts only sections;
 * every other parent accepts the blocks its type allows.
 */
export function insertCandidates(
  metadata: Pick<EditorMetadata, 'sections' | 'blocks'> | null | undefined,
  parentType: string | null,
): DefinitionMeta[] {
  if (!metadata) return []
  if (parentType === null) return metadata.sections ?? []
  return (metadata.blocks ?? []).filter((def) => canContain(parentType, def.type))
}

/**
 * Whether a placement is offerable for a node: it resolves to a target that
 * accepts at least one registered component. `inside` additionally requires the
 * node to be a real container — a leaf's permissive nesting must never surface
 * Add Inside (a Heading can hold nothing even though the policy is permissive for
 * unknown parent types).
 */
export function canInsert(
  node: ComponentNode,
  placement: InsertPlacement,
  slot: SiblingSlot | null,
  parentType: string | null,
  metadata: Pick<EditorMetadata, 'sections' | 'blocks'> | null | undefined,
): boolean {
  if (placement === 'inside' && !canContainChildren(node)) return false
  const target = resolveInsertTarget(node, placement, slot, parentType)
  if (!target) return false
  return insertCandidates(metadata, target.parentType).length > 0
}
