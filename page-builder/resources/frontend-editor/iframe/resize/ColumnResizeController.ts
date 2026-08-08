// Phase 26.2 — Visual column resize controller (iframe side).
//
// Owns the whole resize interaction inside the iframe, mirroring the structure of
// IframeDragController (Phase 22.1): it draws a handle on every divider of the
// active container's columns, runs the pointer lifecycle locally (so real pointer
// capture and LOCAL geometry work — Part 12/16/17), previews the drag live via the
// Phase-17 inline-style technique (flex-basis/max-width/width on the column
// markers — Part 13), and on release asks the shell to commit ONE
// apply-column-layout command (one undo — Part 3/14). It never mutates the layout
// itself.
//
// Handle visibility is driven by the shell's selection/hover of a container; the
// controller re-resolves its group against the live DOM after every reconcile.

import { findMarker, markerMeta, MARKER_SELECTOR } from '../markers'
import {
  DEFAULT_CONSTRAINTS,
  applyDelta,
  equalize,
  normalize,
  toWidthStrings,
  type ResizeConstraints,
} from './geometry'

const COLUMN_TYPE = 'column'
const HANDLE_CLASS = 'pb-fe-col-resize'
const TOOLTIP_CLASS = 'pb-fe-col-resize-tooltip'
const DRAGGING_CLASS = 'pb-fe-col-resizing'

export type ResizeCommit = (containerId: string, widths: string[]) => void

interface ColumnRef {
  id: string
  el: HTMLElement
}

interface ResizeGroup {
  containerId: string
  columns: ColumnRef[]
}

interface Original {
  el: HTMLElement
  flexBasis: string
  maxWidth: string
  width: string
}

interface DragSession {
  index: number
  startX: number
  rowWidthPx: number
  startWidths: number[]
  widths: number[]
  originals: Original[]
  committed: boolean
}

/** A pointer-ish event we can read position + modifiers from (mouse/touch/pen). */
interface PointerLike {
  clientX: number
  clientY: number
  shiftKey?: boolean
  altKey?: boolean
  pointerId?: number
  preventDefault?: () => void
}

export class ColumnResizeController {
  private group: ResizeGroup | null = null
  private readonly handles: HTMLElement[] = []
  private tooltip: HTMLElement | null = null
  private session: DragSession | null = null
  private raf: number | null = null
  private lastSelectedId: string | null = null
  private lastHoveredId: string | null = null

  constructor(
    private readonly canvas: HTMLElement,
    private readonly commit: ResizeCommit,
    private readonly constraints: ResizeConstraints = DEFAULT_CONSTRAINTS,
  ) {}

  /** True while a handle drag is in flight (so the runtime can suppress hover). */
  get dragging(): boolean {
    return this.session !== null
  }

  /**
   * Reconcile the visible handles with the shell's current selection/hover. A
   * selected (or hovered) container — or a selected/hovered column, resolved up to
   * its container — shows resize handles. Rebuilds only when the resolved group
   * changes; otherwise just repositions.
   */
  sync(selectedId: string | null, hoveredId: string | null): void {
    this.lastSelectedId = selectedId
    this.lastHoveredId = hoveredId
    if (this.session) return // never rebuild mid-drag

    const next = this.resolveGroup(selectedId) ?? this.resolveGroup(hoveredId)
    if (!next) {
      this.group = null
      this.clearHandles()
      return
    }
    if (this.sameGroup(next)) {
      this.group = next // refresh element refs (DOM may have been replaced)
      this.reflow()
      return
    }
    this.group = next
    this.buildHandles()
  }

  /** Re-resolve against the live DOM after a reconcile/full render swap. */
  refresh(): void {
    this.sync(this.lastSelectedId, this.lastHoveredId)
  }

  /** Reposition handles to the current column rects (scroll/resize/reflow). */
  reflow(): void {
    if (!this.group) return
    // Elements may have been replaced by a reconcile; re-resolve if stale.
    if (!this.group.columns.every((c) => document.body.contains(c.el))) {
      this.refresh()
      return
    }
    this.positionHandles()
  }

  destroy(): void {
    this.endDrag(false)
    this.clearHandles()
  }

  // ---- Group resolution ------------------------------------------------

  private resolveGroup(id: string | null): ResizeGroup | null {
    if (!id) return null
    const marker = findMarker(this.canvas, id)
    if (!marker) return null

    const meta = markerMeta(marker)
    const anchor =
      meta?.type === COLUMN_TYPE
        ? marker // a column: use its own sibling row
        : (marker.querySelector(`[data-pb-component-type="${COLUMN_TYPE}"]`) as HTMLElement | null)
    if (!anchor) return null

    const parent = anchor.parentElement
    if (!parent) return null

    const columns: ColumnRef[] = Array.from(parent.children)
      .filter(
        (child): child is HTMLElement =>
          child instanceof HTMLElement &&
          child.getAttribute('data-pb-component-type') === COLUMN_TYPE,
      )
      .map((el) => ({ id: el.getAttribute('data-pb-component-id') ?? '', el }))
      .filter((c) => c.id !== '')
      .sort((a, b) => a.el.getBoundingClientRect().left - b.el.getBoundingClientRect().left)

    if (columns.length < 2) return null

    // The container node is the columns' nearest ancestor marker (a "container"
    // block or a "columns" section — apply-column-layout resolves either).
    const containerMarker = parent.closest(MARKER_SELECTOR)
    const containerId = containerMarker?.getAttribute('data-pb-component-id') ?? ''
    if (!containerId) return null

    return { containerId, columns }
  }

  private sameGroup(next: ResizeGroup): boolean {
    if (!this.group) return false
    if (this.group.containerId !== next.containerId) return false
    if (this.group.columns.length !== next.columns.length) return false
    return this.group.columns.every((c, i) => c.id === next.columns[i].id)
  }

  // ---- Handles ---------------------------------------------------------

  private buildHandles(): void {
    this.clearHandles()
    if (!this.group) return
    const dividers = this.group.columns.length - 1
    for (let i = 0; i < dividers; i += 1) {
      const handle = document.createElement('div')
      handle.className = HANDLE_CLASS
      handle.dataset.index = String(i)
      handle.setAttribute('role', 'separator')
      handle.setAttribute('aria-orientation', 'vertical')
      handle.setAttribute('tabindex', '0')
      handle.setAttribute('aria-label', `Resize columns ${i + 1} and ${i + 2}`)
      handle.setAttribute('aria-valuemin', String(this.constraints.minPercent))
      handle.setAttribute('aria-valuemax', String(round(100 - this.constraints.minPercent)))
      handle.addEventListener('pointerdown', this.onHandleDown)
      handle.addEventListener('dblclick', this.onHandleDblClick)
      handle.addEventListener('keydown', this.onHandleKey)
      document.body.appendChild(handle)
      this.handles.push(handle)
    }
    this.positionHandles()
  }

  private clearHandles(): void {
    for (const handle of this.handles) handle.remove()
    this.handles.length = 0
    this.hideTooltip()
  }

  private positionHandles(): void {
    if (!this.group) return
    const cols = this.group.columns
    for (const handle of this.handles) {
      const i = Number(handle.dataset.index)
      const a = cols[i]?.el.getBoundingClientRect()
      const b = cols[i + 1]?.el.getBoundingClientRect()
      if (!a || !b) {
        handle.style.display = 'none'
        continue
      }
      const top = Math.min(a.top, b.top)
      const height = Math.max(a.bottom, b.bottom) - top
      const centre = (a.right + b.left) / 2
      handle.style.display = 'block'
      handle.style.top = `${top + window.scrollY}px`
      handle.style.left = `${centre + window.scrollX}px`
      handle.style.height = `${height}px`
      handle.setAttribute('aria-valuenow', String(round(this.currentPercents()[i] ?? 0)))
    }
  }

  /** Current column widths as percentages, measured from the live rects. */
  private currentPercents(): number[] {
    if (!this.group) return []
    return normalize(this.group.columns.map((c) => c.el.getBoundingClientRect().width))
  }

  // ---- Drag lifecycle --------------------------------------------------

  private onHandleDown = (event: Event): void => {
    const pointer = event as unknown as PointerLike
    const handle = event.currentTarget as HTMLElement
    if (!this.group || this.session) return
    const index = Number(handle.dataset.index)
    if (!Number.isInteger(index)) return

    pointer.preventDefault?.()
    handle.focus?.()
    capture(handle, pointer.pointerId)

    const rowWidthPx = this.group.columns.reduce(
      (acc, c) => acc + c.el.getBoundingClientRect().width,
      0,
    )
    const startWidths = this.currentPercents()
    this.session = {
      index,
      startX: pointer.clientX,
      rowWidthPx: rowWidthPx > 0 ? rowWidthPx : 1,
      startWidths,
      widths: startWidths.slice(),
      originals: this.captureOriginals(),
      committed: false,
    }

    document.addEventListener('pointermove', this.onMove, true)
    document.addEventListener('pointerup', this.onUp, true)
    document.addEventListener('pointercancel', this.onCancel, true)
    document.addEventListener('keydown', this.onDragKey, true)
    document.documentElement.classList.add(DRAGGING_CLASS)
    this.showTooltip()
    this.updateTooltip(pointer.clientY)
  }

  private onMove = (event: Event): void => {
    const pointer = event as unknown as PointerLike
    if (!this.session || !this.group) return
    pointer.preventDefault?.()
    const deltaPercent =
      ((pointer.clientX - this.session.startX) / this.session.rowWidthPx) * 100
    this.session.widths = applyDelta(this.session.startWidths, this.session.index, deltaPercent, {
      minPercent: this.constraints.minPercent,
      snapPercent: pointer.shiftKey ? 0 : this.constraints.snapPercent,
      alt: pointer.altKey === true,
    })
    this.requestPreview(pointer.clientY)
  }

  private onUp = (event: Event): void => {
    const pointer = event as unknown as PointerLike
    pointer.preventDefault?.()
    this.endDrag(true)
  }

  private onCancel = (): void => {
    this.endDrag(false)
  }

  private onDragKey = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
      event.preventDefault()
      this.endDrag(false) // Escape cancels — restore original widths (Part 19)
    }
  }

  /**
   * Finish the drag. `commit` true → send exactly one apply-column-layout and
   * leave the preview in place (the authoritative re-render replaces it, so no
   * flash back to the old widths). `commit` false → restore the original widths.
   */
  private endDrag(commit: boolean): void {
    const session = this.session
    if (!session) return
    this.session = null
    if (this.raf !== null) {
      cancelRaf(this.raf)
      this.raf = null
    }
    document.removeEventListener('pointermove', this.onMove, true)
    document.removeEventListener('pointerup', this.onUp, true)
    document.removeEventListener('pointercancel', this.onCancel, true)
    document.removeEventListener('keydown', this.onDragKey, true)
    document.documentElement.classList.remove(DRAGGING_CLASS)
    this.hideTooltip()

    if (commit && !session.committed && this.group) {
      session.committed = true
      this.applyPreview(session.widths)
      this.commit(this.group.containerId, toWidthStrings(session.widths))
    } else if (!commit) {
      this.restoreOriginals(session.originals)
      this.positionHandles()
    }
  }

  // ---- Double-click equalise (Part 7) ----------------------------------

  private onHandleDblClick = (event: Event): void => {
    event.preventDefault()
    if (!this.group || this.session) return
    const widths = equalize(this.group.columns.length)
    this.applyPreview(widths)
    this.commit(this.group.containerId, toWidthStrings(widths))
  }

  // ---- Keyboard resize (Part 15) ---------------------------------------

  private onHandleKey = (event: KeyboardEvent): void => {
    if (this.session) return
    const handle = event.currentTarget as HTMLElement
    const index = Number(handle.dataset.index)
    const dir = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0
    if (dir === 0 || !this.group) return

    event.preventDefault()
    const step = event.shiftKey ? 1 : this.constraints.snapPercent
    const widths = applyDelta(this.currentPercents(), index, dir * step, {
      minPercent: this.constraints.minPercent,
      snapPercent: event.shiftKey ? 0 : this.constraints.snapPercent,
      alt: event.altKey === true,
    })
    this.applyPreview(widths)
    this.commit(this.group.containerId, toWidthStrings(widths))
  }

  // ---- Live preview (Phase 17 inline-style reuse) ----------------------

  private captureOriginals(): Original[] {
    if (!this.group) return []
    return this.group.columns.map((c) => ({
      el: c.el,
      flexBasis: c.el.style.getPropertyValue('flex-basis'),
      maxWidth: c.el.style.getPropertyValue('max-width'),
      width: c.el.style.getPropertyValue('width'),
    }))
  }

  private restoreOriginals(originals: Original[]): void {
    for (const o of originals) {
      setOrClear(o.el, 'flex-basis', o.flexBasis)
      setOrClear(o.el, 'max-width', o.maxWidth)
      setOrClear(o.el, 'width', o.width)
    }
  }

  private applyPreview(widths: number[]): void {
    if (!this.group) return
    this.group.columns.forEach((c, i) => {
      const value = `${round(widths[i] ?? 0)}%`
      c.el.style.setProperty('flex-basis', value)
      c.el.style.setProperty('max-width', value)
      c.el.style.setProperty('width', value)
    })
  }

  private requestPreview(clientY: number): void {
    if (this.raf !== null) return
    this.raf = requestRaf(() => {
      this.raf = null
      if (!this.session) return
      this.applyPreview(this.session.widths)
      this.positionHandles()
      this.updateTooltip(clientY)
    })
  }

  // ---- Tooltip (Part 9) ------------------------------------------------

  private showTooltip(): void {
    if (this.tooltip) return
    const tip = document.createElement('div')
    tip.className = TOOLTIP_CLASS
    document.body.appendChild(tip)
    this.tooltip = tip
  }

  private hideTooltip(): void {
    this.tooltip?.remove()
    this.tooltip = null
  }

  private updateTooltip(clientY: number): void {
    if (!this.tooltip || !this.session || !this.group) return
    const i = this.session.index
    const left = round(this.session.widths[i] ?? 0)
    const right = round(this.session.widths[i + 1] ?? 0)
    this.tooltip.textContent = `${left}% / ${right}%`
    const a = this.group.columns[i].el.getBoundingClientRect()
    const b = this.group.columns[i + 1].el.getBoundingClientRect()
    const centre = (a.right + b.left) / 2
    this.tooltip.style.left = `${centre + window.scrollX}px`
    this.tooltip.style.top = `${clientY + window.scrollY}px`
  }
}

// ---- Small DOM/number helpers -----------------------------------------

/** Round to 2dp for display/aria (widths are stored at full precision on commit). */
function round(n: number): number {
  return Math.round(n * 100) / 100
}

function setOrClear(el: HTMLElement, prop: string, value: string): void {
  if (value === '') el.style.removeProperty(prop)
  else el.style.setProperty(prop, value)
}

function capture(el: HTMLElement, pointerId: number | undefined): void {
  if (pointerId === undefined) return
  try {
    ;(el as unknown as { setPointerCapture?: (id: number) => void }).setPointerCapture?.(pointerId)
  } catch {
    // jsdom / unsupported: pointer capture is a nicety, not required for correctness.
  }
}

function requestRaf(cb: () => void): number {
  if (typeof requestAnimationFrame === 'function') return requestAnimationFrame(cb)
  return setTimeout(cb, 0) as unknown as number
}

function cancelRaf(handle: number): void {
  if (typeof cancelAnimationFrame === 'function') cancelAnimationFrame(handle)
  else clearTimeout(handle)
}
