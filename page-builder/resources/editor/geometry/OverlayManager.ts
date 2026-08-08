// Phase 13 — Overlay manager.
//
// Owns the geometry engine for the visual canvas and drives every overlay from
// it. Overlay Vue components read rects through this manager (and the shared
// GeometryManager) and must never call getBoundingClientRect themselves — this
// is the one place that decides *when* geometry is re-measured:
//
//   ResizeObserver / MutationObserver / scroll / resize  ->  schedule (rAF)
//                                                        ->  refresh (measure)
//                                                        ->  notify (bump version)
//
// It also exposes the seams drag-and-drop (a later phase) will need —
// dropTargets / nearestEdge / insertionPoint — returning geometry-derived
// placeholder data today, with no drag behaviour attached.

import { GeometryManager, type GeometryRect } from './GeometryManager'
import { GeometryObserver } from './GeometryObserver'
import { HitTest } from './HitTest'

export interface DropTarget {
  id: string
  kind: string
  rect: GeometryRect
}

export type Edge = 'before' | 'after' | 'inside'

export interface EdgeHit {
  id: string
  edge: Edge
  rect: GeometryRect
}

export interface InsertionPoint {
  parentId: string | null
  index: number
}

type ChangeListener = (version: number) => void
type RafFn = (cb: () => void) => number
type CancelRafFn = (handle: number) => void

export class OverlayManager {
  readonly geometry: GeometryManager
  readonly hitTest: HitTest

  private observer: GeometryObserver
  private viewport: HTMLElement | null = null
  private host: HTMLElement | null = null
  private listener: ChangeListener | null = null

  private frame: number | null = null
  private readonly raf: RafFn
  private readonly cancelRaf: CancelRafFn

  private readonly onScroll = () => this.scheduleRefresh()
  private readonly onResize = () => this.scheduleRefresh()

  constructor(geometry: GeometryManager = new GeometryManager()) {
    this.geometry = geometry
    this.hitTest = new HitTest(geometry)
    this.observer = new GeometryObserver(() => this.scheduleRefresh())

    // rAF may be absent in non-browser test envs; fall back to a microtask.
    const g = globalThis as {
      requestAnimationFrame?: RafFn
      cancelAnimationFrame?: CancelRafFn
    }
    this.raf = g.requestAnimationFrame?.bind(globalThis) ?? ((cb) => {
      Promise.resolve().then(cb)
      return 0
    })
    this.cancelRaf = g.cancelAnimationFrame?.bind(globalThis) ?? (() => {})
  }

  /** Notified (with the new geometry version) after every refresh. */
  setListener(listener: ChangeListener | null): void {
    this.listener = listener
  }

  /**
   * Bind the overlay engine to the live canvas. `viewport` is the scroll
   * container overlays are positioned against; `host` is the element whose
   * innerHTML holds the rendered markers.
   */
  attach(viewport: HTMLElement, host: HTMLElement): void {
    this.detach()
    this.viewport = viewport
    this.host = host
    this.geometry.setViewport(viewport)

    this.observer.observe(host)
    this.observer.observeSize(viewport)
    viewport.addEventListener('scroll', this.onScroll, { passive: true })
    window.addEventListener('resize', this.onResize)

    this.sync()
  }

  detach(): void {
    if (this.frame !== null) {
      this.cancelRaf(this.frame)
      this.frame = null
    }
    this.observer.disconnect()
    this.viewport?.removeEventListener('scroll', this.onScroll)
    window.removeEventListener('resize', this.onResize)
    this.viewport = null
    this.host = null
  }

  /** Re-scan the host markers (after a preview swap) and refresh. */
  sync(): void {
    if (!this.host) return
    this.geometry.sync(this.host)
    this.notify()
  }

  /** Coalesce many change signals into a single measured refresh per frame. */
  scheduleRefresh(): void {
    if (this.frame !== null) return
    this.frame = this.raf(() => {
      this.frame = null
      this.refreshNow()
    })
  }

  /** Measure immediately (outside the frame loop). */
  refreshNow(): void {
    this.geometry.refresh()
    this.notify()
  }

  private notify(): void {
    this.listener?.(this.geometry.currentVersion())
  }

  rect(id: string | null): GeometryRect | null {
    return this.geometry.rect(id)
  }

  // ---- Drag preparation (Phase 13 seams; placeholder data, no behaviour) ----

  /** Every container a future drag could drop into, with its geometry. */
  dropTargets(): DropTarget[] {
    const targets: DropTarget[] = []
    for (const entry of this.geometry.allEntries()) {
      if (!entry.visible) continue
      const isContainer = entry.kind === 'section' || entry.kind === 'container'
      if (!isContainer) continue
      const rect = this.geometry.rect(entry.id)
      if (rect) targets.push({ id: entry.id, kind: entry.kind, rect })
    }
    return targets
  }

  /**
   * The component edge nearest a point — the raw signal a drop indicator will
   * later render. Placeholder: picks the hovered component and the closer of
   * its top/bottom edges. No insertion is performed.
   */
  nearestEdge(x: number, y: number): EdgeHit | null {
    const id = this.hitTest.componentAt(x, y)
    if (!id) return null
    const rect = this.geometry.rect(id)
    if (!rect) return null
    const edge: Edge = y - rect.top < rect.bottom - y ? 'before' : 'after'
    return { id, edge, rect }
  }

  /**
   * Where a dragged component would be inserted. Placeholder returning a null
   * parent / zero index until the drag phase gives it meaning.
   */
  insertionPoint(_x: number, _y: number): InsertionPoint {
    return { parentId: null, index: 0 }
  }
}
