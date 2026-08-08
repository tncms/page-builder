// Phase 22.1 — Cross-iframe drop resolver (iframe side). Mirrors the structure
// editor's DropManager rules, but resolves against the real rendered markers
// instead of a cached geometry engine:
//
//   • a section may only be reordered at the root
//   • a block must live inside a section or container block
//   • nothing may drop into itself or one of its own descendants
//   • an empty container still accepts an inside drop
//
// Pure read: safe to call on every pointermove. Never mutates the DOM/layout.

import { canContain, prefersInsideContainer } from '../../../editor/utils/nestingPolicy'
import { markerMeta, findMarker } from '../markers'
import { deepestMarkerAt, parentMarker, rootMarkerOf, rectOf, edgeFor } from './IframeDropGeometry'
import { invalidResolution, type DragSubject, type DropContext, type DropEdge, type IframeDropResolution } from './types'

export class IframeDropResolver {
  constructor(
    private readonly canvas: HTMLElement,
    private readonly ctx: DropContext,
  ) {}

  resolve(subject: DragSubject, x: number, y: number): IframeDropResolution {
    const targetEl = deepestMarkerAt(this.canvas, x, y)
    if (!targetEl) {
      // Empty gaps: a section can still land at the root; a block cannot.
      if (subject.kind === 'section' && this.withinCanvas(x, y)) return this.rootDrop()
      return invalidResolution('No drop target')
    }

    const targetMeta = markerMeta(targetEl)
    if (!targetMeta) return invalidResolution('Unknown target')
    if (targetMeta.id === subject.id) return invalidResolution('Cannot drop onto itself')

    // Cycle guard: never drop onto a descendant of the dragged component. Return
    // a rect so the shell can paint a red marker over the illegal target.
    if (subject.source !== 'palette') {
      const subjectEl = findMarker(this.canvas, subject.id)
      if (subjectEl && subjectEl.contains(targetEl)) {
        return {
          valid: false,
          reason: 'Cannot drop into its own child',
          edge: 'inside',
          targetId: targetMeta.id,
          referenceId: targetMeta.id,
          parentId: null,
          markerRect: rectOf(targetEl),
        }
      }
    }

    const resolution = subject.kind === 'section'
      ? this.resolveSection(subject, targetEl, targetMeta.id, y)
      : this.resolveBlock(targetEl, targetMeta.id, targetMeta.kind, subject.type, y)

    // Composition nesting guard (§10): mirrors the server + Structure Editor.
    // Rejects Element → Container, Column → Column, Container → Column, etc. so
    // the shell paints an invalid (red) marker instead of a valid one.
    if (resolution.valid && resolution.parentId && subject.type) {
      const parentEl = findMarker(this.canvas, resolution.parentId)
      const parentType = parentEl ? markerMeta(parentEl)?.type ?? '' : ''
      if (parentType && !canContain(parentType, subject.type)) {
        return {
          valid: false,
          reason: `A ${subject.type} cannot go inside a ${parentType}`,
          edge: resolution.edge,
          targetId: resolution.targetId,
          referenceId: resolution.referenceId,
          parentId: resolution.parentId,
          markerRect: resolution.markerRect,
        }
      }
    }

    return resolution
  }

  // Sections only reorder at the root: snap to the root-section ancestor of
  // whatever is under the pointer and pick the nearer half.
  private resolveSection(
    subject: DragSubject,
    targetEl: HTMLElement,
    targetId: string,
    y: number,
  ): IframeDropResolution {
    const rootEl = rootMarkerOf(targetEl, this.canvas)
    const rootMeta = rootEl ? markerMeta(rootEl) : null
    if (!rootEl || !rootMeta || rootMeta.id === subject.id) {
      return invalidResolution('Sections can only be reordered at the root')
    }
    const rect = rectOf(rootEl)
    const edge: DropEdge = y < rect.top + rect.height / 2 ? 'before' : 'after'
    return { valid: true, reason: null, edge, targetId, referenceId: rootMeta.id, parentId: null, markerRect: rect }
  }

  private resolveBlock(
    targetEl: HTMLElement,
    targetId: string,
    targetKind: string,
    subjectType: string,
    y: number,
  ): IframeDropResolution {
    const isContainer = targetKind === 'section' || this.ctx.isContainer(targetId)
    const parentEl = parentMarker(targetEl, this.canvas)
    const targetParentId = parentEl ? markerMeta(parentEl)?.id ?? null : null
    const rect = rectOf(targetEl)

    let edge = edgeFor(rect, y, isContainer)
    let parentId: string | null
    const referenceId = targetId

    if (isContainer) {
      if (edge === 'inside') {
        parentId = targetId
      } else if (targetParentId === null) {
        // Before/after a root section would place a block at the root, which is
        // illegal — treat it as a drop *inside* the section instead.
        edge = 'inside'
        parentId = targetId
      } else if (prefersInsideContainer(subjectType, markerMeta(targetEl)?.type ?? '')) {
        // Nearest valid parent (Phase 26.3.7): an element dropped on the edge of a
        // nested container (e.g. a Column) belongs INSIDE it, not as a sibling of
        // that container in its parent — which would render outside the layout.
        edge = 'inside'
        parentId = targetId
      } else {
        // Before/after a nested container: land as a sibling in its parent, with
        // the line drawn against the target itself.
        parentId = targetParentId
      }
    } else {
      // Leaf block: only before/after, dropping into the block's own parent.
      parentId = targetParentId
    }

    if (parentId === null) return invalidResolution('A block cannot be dropped at the root')

    return { valid: true, reason: null, edge, targetId, referenceId, parentId, markerRect: rect }
  }

  private rootDrop(): IframeDropResolution {
    const r = this.canvas.getBoundingClientRect()
    return {
      valid: true,
      reason: null,
      edge: 'inside',
      targetId: null,
      referenceId: null,
      parentId: null,
      markerRect: { top: r.top, left: r.left, width: r.width, height: r.height },
    }
  }

  private withinCanvas(x: number, y: number): boolean {
    const r = this.canvas.getBoundingClientRect()
    return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom
  }
}
