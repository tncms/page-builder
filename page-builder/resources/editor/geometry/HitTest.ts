// Phase 13 — Hit-test engine.
//
// Maps a point (in viewport coordinates, i.e. raw clientX/clientY) to the
// component(s) underneath it, reading only cached geometry — never the DOM.
// Selection today clicks markers directly; drag/drop tomorrow will ask "what is
// under the cursor" mid-gesture when there is no click target, which is what
// this provides. Hidden components are ignored; results are ordered deepest
// (most specific) first, matching what a user expects to grab.

import type { GeometryManager } from './GeometryManager'
import type { CachedRect } from './GeometryCache'

export class HitTest {
  constructor(private readonly manager: GeometryManager) {}

  private contains(rect: CachedRect, x: number, y: number): boolean {
    return x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom
  }

  /**
   * Every component whose box contains the point, deepest first. Uses cached
   * viewport-space rects, so the point must be in viewport coordinates.
   */
  componentsAt(x: number, y: number): string[] {
    const cache = this.manager.getCache()
    const hits: { id: string; depth: number }[] = []

    for (const entry of this.manager.allEntries()) {
      if (!entry.visible) continue
      const rect = cache.get(entry.id)
      if (rect && this.contains(rect, x, y)) {
        hits.push({ id: entry.id, depth: entry.depth })
      }
    }

    hits.sort((a, b) => b.depth - a.depth)
    return hits.map((h) => h.id)
  }

  /** The single most specific (deepest, topmost) component at the point. */
  componentAt(x: number, y: number): string | null {
    return this.componentsAt(x, y)[0] ?? null
  }

  /** The registered parent of a component, or null at the root. */
  parentOf(id: string): string | null {
    return this.manager.entry(id)?.parentId ?? null
  }
}
