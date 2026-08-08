// Phase 16 — Lightweight selection/hover overlay drawn inside the iframe.
//
// Two absolutely-positioned, non-interactive boxes track the selected and
// hovered markers. They are positioned from getBoundingClientRect + page scroll
// so they follow the real rendered component. No layout is mutated; the overlay
// is purely decorative and never intercepts pointer events.

import { findMarker } from './markers'

export class Overlay {
  private readonly selectionBox: HTMLDivElement

  private readonly hoverBox: HTMLDivElement

  private selectedId: string | null = null

  private hoveredId: string | null = null

  constructor(private readonly root: HTMLElement) {
    this.selectionBox = this.createBox('pb-fe-overlay pb-fe-overlay--selected')
    this.hoverBox = this.createBox('pb-fe-overlay pb-fe-overlay--hover')
    document.body.appendChild(this.selectionBox)
    document.body.appendChild(this.hoverBox)
  }

  setSelected(id: string | null): void {
    this.selectedId = id
    this.reposition()
  }

  setHovered(id: string | null): void {
    // Never draw a hover box over the current selection (avoids double borders).
    this.hoveredId = id && id !== this.selectedId ? id : null
    this.reposition()
  }

  /** Re-measure both tracked markers and move the boxes to match. */
  reposition(): void {
    this.place(this.selectionBox, this.selectedId)
    this.place(this.hoverBox, this.hoveredId)
  }

  destroy(): void {
    this.selectionBox.remove()
    this.hoverBox.remove()
  }

  private place(box: HTMLDivElement, id: string | null): void {
    const el = id ? findMarker(this.root, id) : null
    if (!el) {
      box.style.display = 'none'
      return
    }

    const rect = el.getBoundingClientRect()
    box.style.display = 'block'
    box.style.top = `${rect.top + window.scrollY}px`
    box.style.left = `${rect.left + window.scrollX}px`
    box.style.width = `${rect.width}px`
    box.style.height = `${rect.height}px`
  }

  private createBox(className: string): HTMLDivElement {
    const box = document.createElement('div')
    box.className = className
    box.style.display = 'none'
    return box
  }
}
