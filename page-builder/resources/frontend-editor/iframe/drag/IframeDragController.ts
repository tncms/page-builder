// Phase 22.1 — Cross-iframe drag controller (iframe side).
//
// A drag begins in the shell (toolbar handle or Elements palette) but the
// pointer immediately crosses into this iframe, where browser pointer capture
// does NOT reach. So on `pb:drag-start` this controller takes over the pointer
// lifecycle *inside* the iframe: it listens on the iframe document, resolves the
// drop on every move, streams pointer + target back to the shell, auto-scrolls
// near the edges, and reports the terminal drop / cancel. It never mutates the
// layout — the shell commits exactly one command on `pb:drag-drop`.

import { findMarker, markerMeta, MARKER_SELECTOR } from '../markers'
import { AutoScroll } from '../../../editor/drag/AutoScroll'
import { IframeDropResolver } from './IframeDropResolver'
import { markerCandidatesAt, rectOf } from './IframeDropGeometry'
import { pbDebug, pbDebugEnabled } from '../../debug'
import type { BridgeMessage } from '../../bridge/messages'
import type { DragSubject, IframeDropResolution } from './types'

type Sender = (type: string, payload?: unknown) => void

export class IframeDragController {
  private active = false
  private subject: DragSubject | null = null
  private resolver: IframeDropResolver | null = null
  private highlighted: HTMLElement | null = null
  private pointer = { x: 0, y: 0 }
  private velocity = { vx: 0, vy: 0 }
  private raf: number | null = null
  private readonly autoscroll = new AutoScroll()

  constructor(
    private readonly canvas: HTMLElement,
    private readonly send: Sender,
  ) {}

  get dragging(): boolean {
    return this.active
  }

  /** Route the shell→iframe drag messages. */
  onShellMessage(message: BridgeMessage): boolean {
    switch (message.type) {
      case 'pb:drag-start':
        this.start(message.payload)
        return true
      case 'pb:drag-cancel':
      case 'pb:drag-end':
        this.stop()
        return true
      case 'pb:drag-highlight-target':
        this.applyHighlight(message.payload as IframeDropResolution | null)
        return true
      default:
        return false
    }
  }

  private start(payload: unknown): void {
    const p = payload as { subject?: DragSubject; containerIds?: string[] } | undefined
    if (!p?.subject) return

    const containers = new Set(Array.isArray(p.containerIds) ? p.containerIds : [])
    this.subject = p.subject
    this.resolver = new IframeDropResolver(this.canvas, { isContainer: (id) => containers.has(id) })
    this.active = true

    document.addEventListener('pointermove', this.onMove, true)
    document.addEventListener('pointerup', this.onUp, true)
    document.addEventListener('pointercancel', this.onPointerCancel, true)
    document.addEventListener('keydown', this.onKey, true)
    document.addEventListener('pointerleave', this.onLeave, true)
    document.documentElement.classList.add('pb-fe-dragging')

    // Phase 26.3.13 — DEV/opt-in: dump every drop marker the iframe exposes so a
    // real browser can confirm the target column carries a hittable
    // data-pb-component-id/type="column" rect (not zero-height or missing).
    if (pbDebugEnabled()) {
      const markers = Array.from(this.canvas.querySelectorAll<HTMLElement>(MARKER_SELECTOR)).map((el) => {
        const meta = markerMeta(el)
        const r = rectOf(el)
        return { id: meta?.id, type: meta?.type, kind: meta?.kind, rect: { top: r.top, left: r.left, width: r.width, height: r.height } }
      })
      pbDebug('[PB-DROP-MARKERS]', { count: markers.length, markers })
    }
  }

  private onMove = (event: PointerEvent): void => {
    if (!this.active || !this.resolver || !this.subject) return
    event.preventDefault()
    this.pointer = { x: event.clientX, y: event.clientY }
    this.send('pb:drag-pointer', { x: event.clientX, y: event.clientY })
    this.emitResolution(this.resolver.resolve(this.subject, event.clientX, event.clientY))
    this.runAutoScroll(event.clientX, event.clientY)
  }

  private onUp = (event: PointerEvent): void => {
    if (!this.active || !this.resolver || !this.subject) return
    event.preventDefault()
    // Phase 26.3.13 — DEV/opt-in: at the actual drop point, show which markers
    // contain the pointer and which one wins (deepest → smallest area). This is
    // the primary suspect for "lands beside the column": if the winner is the
    // Columns/Section instead of the column, the geometry is the culprit.
    if (pbDebugEnabled()) {
      const candidates = markerCandidatesAt(this.canvas, event.clientX, event.clientY).map((c) => {
        const meta = markerMeta(c.el)
        return { id: meta?.id, type: meta?.type, depth: c.depth, area: Math.round(c.area) }
      })
      pbDebug('[PB-HITTEST]', {
        x: event.clientX,
        y: event.clientY,
        candidates,
        winner: candidates[0] ?? null,
      })
    }
    const resolution = this.resolver.resolve(this.subject, event.clientX, event.clientY)
    this.send('pb:drag-drop', resolution)
    this.stop()
  }

  private onPointerCancel = (): void => {
    if (!this.active) return
    this.send('pb:drag-cancelled', {})
    this.stop()
  }

  private onKey = (event: KeyboardEvent): void => {
    if (this.active && event.key === 'Escape') {
      event.preventDefault()
      this.send('pb:drag-cancelled', {})
      this.stop()
    }
  }

  // Pointer left the iframe entirely (into the shell chrome): the shell takes
  // over the ghost and clears the marker until the pointer returns.
  private onLeave = (): void => {
    if (!this.active) return
    this.clearHighlight()
    this.send('pb:drag-leave', {})
  }

  // Report the resolution; the shell echoes it back as `pb:drag-highlight-target`
  // so the in-iframe highlight has a single source of truth (the shell can
  // suppress/override it) and the marker stays aligned to real content.
  private emitResolution(resolution: IframeDropResolution): void {
    this.send('pb:drag-target', resolution)
  }

  private applyHighlight(resolution: IframeDropResolution | null): void {
    this.clearHighlight()
    const id = resolution?.referenceId ?? resolution?.targetId ?? null
    if (!id) return
    const el = findMarker(this.canvas, id)
    if (!el) return
    el.classList.add('pb-fe-drop-target')
    el.classList.toggle('pb-fe-drop-target--invalid', !resolution!.valid)
    el.classList.toggle('pb-fe-drop-target--inside', resolution!.edge === 'inside')
    this.highlighted = el
  }

  private clearHighlight(): void {
    if (!this.highlighted) return
    this.highlighted.classList.remove('pb-fe-drop-target', 'pb-fe-drop-target--invalid', 'pb-fe-drop-target--inside')
    this.highlighted = null
  }

  private runAutoScroll(x: number, y: number): void {
    const rect = { top: 0, bottom: window.innerHeight, left: 0, right: window.innerWidth }
    this.velocity = this.autoscroll.velocityFor(rect, x, y)
    if (this.velocity.vx !== 0 || this.velocity.vy !== 0) this.ensureScrollLoop()
  }

  private ensureScrollLoop(): void {
    if (this.raf !== null || typeof requestAnimationFrame !== 'function') return
    const tick = (): void => {
      this.raf = null
      if (!this.active || (this.velocity.vx === 0 && this.velocity.vy === 0)) return
      window.scrollBy(this.velocity.vx, this.velocity.vy)
      // Re-resolve at the held pointer so the marker follows the scrolled content.
      if (this.resolver && this.subject) {
        this.emitResolution(this.resolver.resolve(this.subject, this.pointer.x, this.pointer.y))
      }
      this.raf = requestAnimationFrame(tick)
    }
    this.raf = requestAnimationFrame(tick)
  }

  private stop(): void {
    this.active = false
    this.subject = null
    this.resolver = null
    this.velocity = { vx: 0, vy: 0 }
    this.clearHighlight()
    if (this.raf !== null && typeof cancelAnimationFrame === 'function') {
      cancelAnimationFrame(this.raf)
    }
    this.raf = null
    document.removeEventListener('pointermove', this.onMove, true)
    document.removeEventListener('pointerup', this.onUp, true)
    document.removeEventListener('pointercancel', this.onPointerCancel, true)
    document.removeEventListener('keydown', this.onKey, true)
    document.removeEventListener('pointerleave', this.onLeave, true)
    document.documentElement.classList.remove('pb-fe-dragging')
  }
}
