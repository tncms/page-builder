// Phase 14.4 — Outline-mode drag model.
//
// The visual canvas drags against the geometry engine (measured rects +
// HitTest). Outline mode has no measured geometry — it is a scrolled DOM tree —
// so it drags against *explicit drop zones* instead: thin elements the tree
// renders between/around cards and inside containers, each carrying the parent
// and index it represents. A drop is resolved purely from the zone under the
// pointer, so the resolver here is a pure function: given the dragged subject and
// the zone, it decides whether the drop is legal and where it lands. It mirrors
// the same backend nesting rules the geometry DropManager enforces:
//
//   • a section may only live at the root
//   • a block must live inside a section or container (never the root)
//   • nothing may drop into itself or one of its own descendants
//
// It NEVER mutates the layout — the store commits exactly once, on drop, through
// the Phase 8 command bus.

import type { DragSubject } from './types'

/** A drop zone rendered by the outline tree, decoded from its data attributes. */
export interface OutlineZone {
  /** The container the zone inserts into; null = the layout root. */
  parentId: string | null
  /** The child index the drop would land at within that parent. */
  index: number
  /** 'gap' between siblings, 'inside' an (empty) container. */
  kind: 'gap' | 'inside'
}

/** The resolved outline drop: where it lands and whether it is legal. */
export interface OutlineDropTarget {
  valid: boolean
  reason: string | null
  parentId: string | null
  index: number
  /** Stable identity of the zone, so the matching zone can highlight itself. */
  key: string
}

export interface OutlineResolveOptions {
  /**
   * Whether `parentId` is the subject itself or sits inside the subject's own
   * subtree (dropping there would nest a node inside itself). Palette subjects
   * never trigger this — they aren't in the tree yet.
   */
  isSelfOrDescendant: (parentId: string | null, subjectId: string) => boolean
}

/** Stable key for a zone (used to match the active zone for highlighting). */
export function outlineZoneKey(parentId: string | null, index: number, kind: 'gap' | 'inside'): string {
  return `${parentId ?? 'root'}:${index}:${kind}`
}

/**
 * Resolve where `subject` would land for the zone under the pointer. Pure and
 * side-effect free — safe to call on every pointermove. Returns null when there
 * is no zone under the pointer at all.
 */
export function resolveOutlineDrop(
  subject: DragSubject,
  zone: OutlineZone | null,
  options: OutlineResolveOptions,
): OutlineDropTarget | null {
  if (!zone) return null

  const key = outlineZoneKey(zone.parentId, zone.index, zone.kind)
  const base = { parentId: zone.parentId, index: zone.index, key }

  if (subject.kind === 'section') {
    return zone.parentId === null
      ? { ...base, valid: true, reason: null }
      : { ...base, valid: false, reason: 'Sections can only be placed at the root.' }
  }

  // Blocks and container blocks must live inside a section or container.
  if (zone.parentId === null) {
    return { ...base, valid: false, reason: 'A block needs a section or container to live in.' }
  }

  if (subject.source !== 'palette' && options.isSelfOrDescendant(zone.parentId, subject.id)) {
    return { ...base, valid: false, reason: 'Cannot drop a component into its own child.' }
  }

  return { ...base, valid: true, reason: null }
}
