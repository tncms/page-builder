// Phase 17 — Live DOM patch orchestrator (iframe side).
//
// Receives the granular pb:patch-* messages, resolves the target element within
// the component marker, applies the style/text/attr mutation and records a
// restore fn so clear-patches can revert it. It NEVER changes structure and never
// touches the payload — the authoritative HTML always comes from the full-render
// reconciliation on commit (pb:update-payload), after which reset() drops all
// tracking because the elements have been replaced.

import { findMarker } from './markers'
import { applyStyle, type Restore } from './stylePatcher'
import { applyText } from './textPatcher'
import { applyAttr } from './attrPatcher'

export class DomPatcher {
  // componentId -> restore fns for every inline patch currently applied to it.
  private readonly applied = new Map<string, Restore[]>()

  constructor(private readonly root: HTMLElement) {}

  patchStyle(componentId: string, selector: string | null, style: Record<string, unknown>): void {
    const el = this.target(componentId, selector)
    if (el) this.track(componentId, applyStyle(el, style))
  }

  patchText(componentId: string, selector: string | null, text: string): void {
    const el = this.target(componentId, selector)
    if (el) this.track(componentId, applyText(el, text))
  }

  patchAttr(componentId: string, selector: string | null, attr: string, value: string): void {
    const el = this.target(componentId, selector)
    if (el) this.track(componentId, applyAttr(el, attr, value))
  }

  /** Revert every inline patch applied to a component and forget it. */
  clear(componentId: string): void {
    const restores = this.applied.get(componentId)
    if (!restores) return
    // Undo in reverse so overlapping patches restore cleanly.
    for (let i = restores.length - 1; i >= 0; i -= 1) restores[i]()
    this.applied.delete(componentId)
  }

  /** Drop all tracking without reverting — used after a full innerHTML swap. */
  reset(): void {
    this.applied.clear()
  }

  private track(componentId: string, restore: Restore): void {
    const list = this.applied.get(componentId) ?? []
    list.push(restore)
    this.applied.set(componentId, list)
  }

  /** Resolve the target element: a selector within the marker, or the root. */
  private target(componentId: string, selector: string | null): HTMLElement | null {
    const marker = findMarker(this.root, componentId)
    if (!marker) return null

    if (selector) {
      const el = marker.querySelector(selector)
      return el instanceof HTMLElement ? el : null
    }

    // null selector => the rendered root element (the marker's first child), or
    // the marker itself when it has no element child.
    const first = marker.firstElementChild
    return first instanceof HTMLElement ? first : marker
  }
}
