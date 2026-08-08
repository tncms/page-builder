import { canContain } from './nestingPolicy'

/**
 * Drag & Drop decision model (Phase 26.3, Increment 7).
 *
 * A single, normalized description of what a drop is ABOUT to do, plus the one
 * pure function that decides whether it may commit. Every drag surface — the
 * visual canvas, the outline tree, and the live iframe — already resolves a
 * geometry-specific "resolution" (DropResolution / OutlineDropTarget /
 * LiveDropResolution). This module distils any of those into ONE contract so the
 * commit-time safety rules (lock, nesting, self/descendant) live in exactly one
 * place instead of being re-derived per surface.
 *
 * It never mutates anything and never touches global state — the same intent +
 * guards always yield the same verdict — so the whole decision is unit-testable
 * without a store, a payload, or a mounted component. The actual structural
 * change still happens once, on drop, through the existing command bus; this only
 * gates it. NestingPolicy (`canContain`) remains the single nesting authority and
 * mirrors the server exactly.
 */

export type DropAction = 'inside' | 'before' | 'after'

export interface DropIntent {
  /** 'palette' = create a brand-new component; 'move' = relocate an existing node. */
  mode: 'palette' | 'move'
  /** The node being moved, or null for a palette insert (nothing exists yet). */
  sourceId: string | null
  /** The component type being placed (the dragged subject's type). */
  sourceType: string
  /** The reference component the pointer is over (a sibling or a container), or null at root. */
  targetId: string | null
  /** The reference component's type, or null when unknown / at root. */
  targetType: string | null
  /** The container the component will actually land in (null = document root). */
  parentId: string | null
  /** Relationship to the reference: into it, or before/after it as a sibling. */
  action: DropAction
}

/** The minimal shape of a dragged subject this module reads. */
interface SubjectLike {
  id: string
  type: string
  source?: string
}

/** The minimal shape of a geometry resolution this module reads (all surfaces share it). */
interface ResolutionLike {
  edge: DropAction | null
  targetId: string | null
  parentId: string | null
}

/**
 * Normalize a dragged subject + a geometry resolution into a {@link DropIntent}.
 * `typeOf` resolves a component id to its type (from the authoritative payload);
 * pass a function that returns null for unknown ids / null.
 */
export function describeDropIntent(
  subject: SubjectLike,
  resolution: ResolutionLike,
  typeOf: (id: string | null) => string | null,
): DropIntent {
  const mode = subject.source === 'palette' ? 'palette' : 'move'
  return {
    mode,
    sourceId: mode === 'palette' ? null : subject.id,
    sourceType: subject.type,
    targetId: resolution.targetId,
    targetType: typeOf(resolution.targetId),
    parentId: resolution.parentId,
    action: resolution.edge ?? 'inside',
  }
}

/** The lookups {@link dropRejection} needs, injected so the function stays pure. */
export interface DropGuards {
  /** The type of the resolved parent container (null at root / unknown). */
  parentType: (parentId: string | null) => string | null
  /** Whether a node id is locked (editor-session lock authority). */
  isLocked: (id: string | null) => boolean
  /** Whether `parentId` is the source node itself or one of its descendants. */
  isSelfOrDescendant: (parentId: string | null, sourceId: string) => boolean
}

/**
 * The single reason a drop must NOT commit, or null when it is allowed. Order:
 * locked target → locked source → move-into-self/descendant → nesting violation.
 * Mirrors — and is a belt-and-suspenders re-assert of — what the geometry
 * resolvers already surface as invalid, so a commit can never slip past them.
 */
export function dropRejection(intent: DropIntent, guards: DropGuards): string | null {
  if (guards.isLocked(intent.parentId)) return 'The drop target is locked.'
  if (intent.mode === 'move' && intent.sourceId) {
    if (guards.isLocked(intent.sourceId)) return 'This component is locked.'
    if (guards.isSelfOrDescendant(intent.parentId, intent.sourceId)) {
      return 'A component cannot be moved into itself.'
    }
  }
  const parentType = guards.parentType(intent.parentId)
  if (parentType && !canContain(parentType, intent.sourceType)) {
    return `A ${intent.sourceType} cannot go inside a ${parentType}.`
  }
  return null
}

/** Convenience boolean form of {@link dropRejection}. */
export function isDropAllowed(intent: DropIntent, guards: DropGuards): boolean {
  return dropRejection(intent, guards) === null
}
