// Phase 14 — Drag manager.
//
// Owns the pointer lifecycle of a drag. On pickup it captures the pointer and
// listens globally (pointermove / pointerup / keydown) so the gesture survives
// leaving the handle; on every move it asks the DropManager to resolve a target
// and lets AutoScroll nudge the canvas; on release it hands the resolution back
// for a single command. Pointer events unify mouse and touch, so touch works as
// long as the handle sets `touch-action: none` (the CSS does).
//
// It NEVER mutates the layout — it only drives the session and reports state
// through callbacks. All committing happens in the store, on drop.

import type { AutoScroll } from './AutoScroll'
import type { DragSession } from './DragSession'
import type { DropManager } from './DropManager'
import type { DragSubject, DropResolution } from './types'

export interface DragCallbacks {
  onStart(subject: DragSubject, x: number, y: number): void
  onMove(x: number, y: number, resolution: DropResolution): void
  onDrop(resolution: DropResolution | null): void
  onCancel(): void
  /** The scroll container the drag operates over (for auto-scroll). */
  getViewport(): HTMLElement | null
}

type EventTargetLike = Pick<Window, 'addEventListener' | 'removeEventListener'>

export class DragManager {
  private callbacks: DragCallbacks | null = null
  private bound = false

  private readonly onPointerMove = (e: PointerEvent): void => this.handleMove(e)
  private readonly onPointerUp = (e: PointerEvent): void => this.handleUp(e)
  private readonly onKeyDown = (e: KeyboardEvent): void => this.handleKey(e)

  constructor(
    private readonly session: DragSession,
    private readonly dropManager: DropManager,
    private readonly autoScroll: AutoScroll,
    private readonly target: EventTargetLike = window,
  ) {}

  /** Wire the manager to the store/canvas. Replaces any previous binding. */
  bind(callbacks: DragCallbacks): void {
    this.unbind()
    this.callbacks = callbacks
  }

  unbind(): void {
    if (this.session.active()) this.teardown()
    this.callbacks = null
  }

  isActive(): boolean {
    return this.session.active()
  }

  /** Whether the manager is wired to a canvas (drag only works once bound). */
  isBound(): boolean {
    return this.callbacks !== null
  }

  /**
   * Begin a drag for `subject` from a pointer-down event. Prevents the default
   * text selection / click-select and starts listening globally.
   */
  start(subject: DragSubject, event: PointerEvent): void {
    if (!this.callbacks || this.session.active()) return
    event.preventDefault()

    this.session.start(subject, event.clientX, event.clientY)
    this.listen()
    this.callbacks.onStart(subject, event.clientX, event.clientY)
  }

  private handleMove(event: PointerEvent): void {
    const subject = this.session.getSubject()
    if (!subject || !this.callbacks) return

    const resolution = this.dropManager.resolve(subject, event.clientX, event.clientY)
    this.session.move(event.clientX, event.clientY, resolution)

    const viewport = this.callbacks.getViewport()
    if (viewport) this.autoScroll.update(viewport, event.clientX, event.clientY)

    this.callbacks.onMove(event.clientX, event.clientY, resolution)
  }

  private handleUp(_event: PointerEvent): void {
    if (!this.session.active() || !this.callbacks) return
    const resolution = this.session.drop()
    this.teardown()
    this.callbacks.onDrop(resolution)
  }

  private handleKey(event: KeyboardEvent): void {
    if (event.key !== 'Escape' || !this.session.active()) return
    event.preventDefault()
    this.session.cancel()
    this.teardown()
    this.callbacks?.onCancel()
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
    this.autoScroll.stop()
    if (!this.bound) return
    this.bound = false
    this.target.removeEventListener('pointermove', this.onPointerMove as EventListener)
    this.target.removeEventListener('pointerup', this.onPointerUp as EventListener)
    this.target.removeEventListener('pointercancel', this.onPointerUp as EventListener)
    this.target.removeEventListener('keydown', this.onKeyDown as EventListener)
  }
}
