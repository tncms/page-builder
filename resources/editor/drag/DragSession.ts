// Phase 14 — Drag session.
//
// The single source of truth for the *state* of an in-progress drag. It is a
// plain data container: it tracks the dragged subject, the live pointer, and the
// currently resolved drop — and nothing else. It never touches the DOM, never
// measures geometry, and never mutates the layout. The DragManager drives it
// from pointer events; the DropManager fills in the resolution; the store reads
// it to render. Keeping it inert makes the whole gesture trivially testable.

import type { DragSubject, DropResolution } from './types'

export class DragSession {
  private subject: DragSubject | null = null
  private pointer = { x: 0, y: 0 }
  private resolution: DropResolution | null = null

  /** Begin tracking a drag from the pickup point. */
  start(subject: DragSubject, x: number, y: number): void {
    this.subject = subject
    this.pointer = { x, y }
    this.resolution = null
  }

  /** Record the latest pointer position and resolved drop (preview only). */
  move(x: number, y: number, resolution: DropResolution): void {
    if (!this.subject) return
    this.pointer = { x, y }
    this.resolution = resolution
  }

  /**
   * End the drag, returning the resolution that was in effect. The session is
   * cleared; the caller decides whether that resolution is worth committing.
   */
  drop(): DropResolution | null {
    const result = this.resolution
    this.reset()
    return result
  }

  /** Abandon the drag with no result (Escape / lost pointer). */
  cancel(): void {
    this.reset()
  }

  active(): boolean {
    return this.subject !== null
  }

  getSubject(): DragSubject | null {
    return this.subject
  }

  getPointer(): { x: number; y: number } {
    return this.pointer
  }

  getResolution(): DropResolution | null {
    return this.resolution
  }

  private reset(): void {
    this.subject = null
    this.resolution = null
  }
}
