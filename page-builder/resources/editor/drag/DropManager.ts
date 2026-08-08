// Phase 14 — Drop manager.
//
// Given the dragged subject and a raw pointer position, decides where it would
// land — the target, the edge (before / after / inside), the resolved parent
// container, and whether the drop is legal. It reads ONLY the geometry engine
// (GeometryManager + HitTest); it never calls querySelector or measures the DOM
// mid-drag, and it never mutates the layout. The nesting rules it enforces mirror
// the backend LayoutMutator so the preview can never promise a drop the command
// would reject:
//
//   • a section may only be reordered at the root
//   • a block must live inside a section or container block
//   • nothing may drop into itself or one of its own descendants
//
// Coordinates: hit-testing runs in client space (raw clientX/clientY, matching
// the cached rects HitTest reads); the marker rect it returns is viewport-local
// (what the overlay layer renders against).

import type { GeometryManager, GeometryRect, HitTest } from '../geometry'
import { canContain, prefersInsideContainer } from '../utils/nestingPolicy'
import { invalidResolution, type DragSubject, type DropEdge, type DropResolution } from './types'

export class DropManager {
  // Container-capability is NOT in the marker (the renderer only ever emits
  // kind 'section'/'block'), so it is injected from the payload. Without this,
  // container blocks — especially empty ones — could never receive a drop.
  private containerCheck: (id: string) => boolean = () => false

  constructor(
    private readonly geometry: GeometryManager,
    private readonly hitTest: HitTest,
  ) {}

  /** Supply the container-capability lookup (from the layout payload). */
  setContainerCheck(check: (id: string) => boolean): void {
    this.containerCheck = check
  }

  private isContainer(id: string, kind: string): boolean {
    return kind === 'section' || kind === 'container' || this.containerCheck(id)
  }

  /**
   * Resolve the drop for `subject` at a client-space point. Pure read: safe to
   * call on every pointermove.
   */
  resolve(subject: DragSubject, clientX: number, clientY: number): DropResolution {
    const targetId = this.hitTest.componentAt(clientX, clientY)
    if (!targetId) {
      // Empty canvas or the gaps around sections: a section can still land at
      // the root; a block cannot (it needs a section/container to live in).
      if (subject.kind === 'section' && this.withinViewport(clientX, clientY)) {
        return this.rootDrop()
      }
      return invalidResolution('No drop target')
    }
    if (targetId === subject.id) return invalidResolution('Cannot drop onto itself')

    const targetEntry = this.geometry.entry(targetId)
    const targetRect = this.geometry.rect(targetId)
    if (!targetEntry || !targetRect) return invalidResolution('Unknown target')

    const localY = clientY - this.geometry.getViewportRect().top

    return subject.kind === 'section'
      ? this.resolveSection(subject, targetId, localY)
      : this.resolveBlock(subject, targetId, this.isContainer(targetId, targetEntry.kind), targetEntry.parentId, targetRect, localY)
  }

  // Sections only reorder at the root: snap to the root-section ancestor of
  // whatever is under the pointer and pick the nearer half.
  private resolveSection(subject: DragSubject, targetId: string, localY: number): DropResolution {
    const rootId = this.rootSectionOf(targetId)
    const rect = rootId ? this.geometry.rect(rootId) : null
    if (!rootId || !rect || rootId === subject.id) {
      return invalidResolution('Sections can only be reordered at the root')
    }
    const edge: DropEdge = localY < rect.top + rect.height / 2 ? 'before' : 'after'
    return { valid: true, reason: null, edge, targetId, referenceId: rootId, parentId: null, markerRect: rect }
  }

  private resolveBlock(
    subject: DragSubject,
    targetId: string,
    isContainer: boolean,
    targetParentId: string | null,
    rect: GeometryRect,
    localY: number,
  ): DropResolution {
    let edge = this.edgeFor(rect, localY, isContainer)

    let parentId: string | null
    let referenceId = targetId

    if (isContainer) {
      if (edge === 'inside') {
        parentId = targetId
        referenceId = targetId
      } else if (targetParentId === null) {
        // Before/after a root section would place a block at the root, which is
        // illegal — treat it as a drop *inside* the section instead.
        edge = 'inside'
        parentId = targetId
        referenceId = targetId
      } else if (prefersInsideContainer(subject.type, this.geometry.entry(targetId)?.type ?? '')) {
        // Nearest valid parent (Phase 26.3.7): an element dropped on the edge of a
        // nested container (e.g. a Column) belongs INSIDE it, not as a sibling of
        // that container in its parent — which would render outside the layout.
        edge = 'inside'
        parentId = targetId
        referenceId = targetId
      } else {
        parentId = targetParentId
      }
    } else {
      // Leaf block: only before/after, dropping into the block's own parent.
      parentId = targetParentId
    }

    if (parentId === null) return invalidResolution('A block cannot be dropped at the root')

    // Cycle guard: never drop into the subject or one of its descendants.
    if (parentId === subject.id || this.isWithinSubtree(parentId, subject.id)) {
      return { valid: false, reason: 'Cannot drop into its own child', edge, targetId, referenceId, parentId, markerRect: rect }
    }

    // Composition nesting guard (§10): mirrors the server. Rejects Element →
    // Container, Column → Column, Container → Column, etc. Permissive for
    // sections / legacy / unknown types.
    const parentType = this.geometry.entry(parentId)?.type ?? ''
    if (parentType && subject.type && !canContain(parentType, subject.type)) {
      return { valid: false, reason: `A ${subject.type} cannot go inside a ${parentType}`, edge, targetId, referenceId, parentId, markerRect: rect }
    }

    return { valid: true, reason: null, edge, targetId, referenceId, parentId, markerRect: rect }
  }

  private edgeFor(rect: GeometryRect, localY: number, isContainer: boolean): DropEdge {
    if (isContainer) {
      const third = rect.height / 3
      if (localY < rect.top + third) return 'before'
      if (localY > rect.bottom - third) return 'after'
      return 'inside'
    }
    return localY < rect.top + rect.height / 2 ? 'before' : 'after'
  }

  /** A valid drop at the layout root (empty canvas / gaps), for section drags. */
  private rootDrop(): DropResolution {
    const vp = this.geometry.getViewportRect()
    const markerRect: GeometryRect = {
      id: '__root__',
      x: 0,
      y: 0,
      left: 0,
      top: 0,
      width: vp.width,
      height: vp.height,
      right: vp.width,
      bottom: vp.height,
    }
    return { valid: true, reason: null, edge: 'inside', targetId: null, referenceId: null, parentId: null, markerRect }
  }

  private withinViewport(x: number, y: number): boolean {
    const vp = this.geometry.getViewportRect()
    return x >= vp.left && x <= vp.right && y >= vp.top && y <= vp.bottom
  }

  /** Walk registered ancestry to the top-most (root) section id. */
  private rootSectionOf(id: string): string | null {
    let entry = this.geometry.entry(id)
    while (entry && entry.parentId) {
      entry = this.geometry.entry(entry.parentId)
    }
    return entry ? entry.id : null
  }

  /** Whether `id` sits anywhere inside `ancestorId`'s subtree. */
  private isWithinSubtree(id: string | null, ancestorId: string): boolean {
    let entry = id ? this.geometry.entry(id) : null
    while (entry) {
      if (entry.id === ancestorId) return true
      entry = entry.parentId ? this.geometry.entry(entry.parentId) : null
    }
    return false
  }
}
