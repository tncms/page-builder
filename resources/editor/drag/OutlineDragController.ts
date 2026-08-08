// Phase 14.4 — Outline drag controller.
//
// Owns the pointer lifecycle of an outline-mode drag, mirroring DragManager but
// resolving against explicit DOM drop zones instead of measured geometry. On
// pickup it captures the gesture and listens globally (pointermove / pointerup /
// keydown) so the drag survives leaving the handle; on every move it hit-tests
// the zone under the pointer via `zoneAt`; on release it hands the last zone back
// for a single command. It NEVER mutates the layout — it only drives callbacks.
//
// Zone hit-testing is injectable so the store can swap a deterministic resolver
// in tests (happy-dom's elementFromPoint is unreliable). In the browser it reads
// the topmost `[data-pb-drop-zone]` under the pointer.

import type { DragSubject } from './types'
import type { OutlineZone } from './outline'

export interface OutlineDragCallbacks {
  onStart(subject: DragSubject, x: number, y: number): void
  onMove(x: number, y: number, zone: OutlineZone | null): void
  onDrop(zone: OutlineZone | null): void
  onCancel(): void
}

type EventTargetLike = Pick<Window, 'addEventListener' | 'removeEventListener'>

export type ZoneResolver = (x: number, y: number) => OutlineZone | null

/** Read the topmost drop zone under a client point from its data attributes. */
export function domZoneAt(x: number, y: number): OutlineZone | null {
  if (typeof document === 'undefined' || typeof document.elementFromPoint !== 'function') return null
  const el = document.elementFromPoint(x, y) as HTMLElement | null
  const zone = el?.closest<HTMLElement>('[data-pb-drop-zone]')
  if (!zone) return null
  const parent = zone.getAttribute('data-pb-drop-parent')
  const kind = zone.getAttribute('data-pb-drop-kind')
  return {
    parentId: parent && parent !== '' ? parent : null,
    index: Number(zone.getAttribute('data-pb-drop-index') ?? 0),
    kind: kind === 'inside' ? 'inside' : 'gap',
  }
}

export class OutlineDragController {
  private callbacks: OutlineDragCallbacks | null = null
  private subject: DragSubject | null = null
  private lastZone: OutlineZone | null = null
  private bound = false

  private readonly onPointerMove = (e: PointerEvent): void => this.handleMove(e)
  private readonly onPointerUp = (e: PointerEvent): void => this.handleUp(e)
  private readonly onKeyDown = (e: KeyboardEvent): void => this.handleKey(e)

  constructor(
    private zoneAt: ZoneResolver = domZoneAt,
    private readonly target: EventTargetLike = window,
  ) {}

  /** Override zone hit-testing (used by tests to inject a deterministic map). */
  setZoneResolver(resolver: ZoneResolver): void {
    this.zoneAt = resolver
  }

  bind(callbacks: OutlineDragCallbacks): void {
    this.unbind()
    this.callbacks = callbacks
  }

  unbind(): void {
    if (this.subject) this.teardown()
    this.callbacks = null
  }

  isBound(): boolean {
    return this.callbacks !== null
  }

  isActive(): boolean {
    return this.subject !== null
  }

  /** Begin an outline drag from a pointer-down event on a handle/palette item. */
  start(subject: DragSubject, event: PointerEvent): void {
    if (!this.callbacks || this.subject) return
    event.preventDefault()

    this.subject = subject
    this.lastZone = null
    this.listen()
    this.callbacks.onStart(subject, event.clientX, event.clientY)
  }

  private handleMove(event: PointerEvent): void {
    if (!this.subject || !this.callbacks) return
    this.lastZone = this.zoneAt(event.clientX, event.clientY)
    this.callbacks.onMove(event.clientX, event.clientY, this.lastZone)
  }

  private handleUp(_event: PointerEvent): void {
    if (!this.subject || !this.callbacks) return
    const zone = this.lastZone
    const callbacks = this.callbacks
    this.teardown()
    callbacks.onDrop(zone)
  }

  private handleKey(event: KeyboardEvent): void {
    if (event.key !== 'Escape' || !this.subject) return
    event.preventDefault()
    const callbacks = this.callbacks
    this.teardown()
    callbacks?.onCancel()
  }

  private listen(): void {
    if (this.bound) return
    this.bound = true
    this.target.addEventListener('pointermove', this.onPointerMove as EventListener)
    this.target.addEventListener('pointerup', this.onPointerUp as EventListener)
    this.target.addEventListener('pointercancel', this.onPointerUp as EventListener)
    this.target.addEventListener('keydown', this.onKeyDown as EventListener)
  }

  private teardown(): void {
    this.subject = null
    this.lastZone = null
    if (!this.bound) return
    this.bound = false
    this.target.removeEventListener('pointermove', this.onPointerMove as EventListener)
    this.target.removeEventListener('pointerup', this.onPointerUp as EventListener)
    this.target.removeEventListener('pointercancel', this.onPointerUp as EventListener)
    this.target.removeEventListener('keydown', this.onKeyDown as EventListener)
  }
}
