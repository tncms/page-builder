// Phase 13 — Geometry cache.
//
// Measuring a DOM element with getBoundingClientRect() forces a synchronous
// layout ("layout thrash"). Doing it for every component on every repaint is
// expensive, so measured rects are cached here and only re-read when something
// invalidates them (a resize, a mutation, a scroll). The cache never measures
// on its own — GeometryManager writes measured rects in, everything else reads
// them out.

export interface CachedRect {
  readonly x: number
  readonly y: number
  readonly width: number
  readonly height: number
  readonly top: number
  readonly left: number
  readonly right: number
  readonly bottom: number
}

interface CacheSlot {
  rect: CachedRect
  stamp: number
}

export class GeometryCache {
  private slots = new Map<string, CacheSlot>()

  // Monotonic invalidation counter used as a coarse "timestamp". A counter is
  // used instead of a wall clock so behaviour is deterministic and testable.
  private stamp = 0

  /** Store a freshly measured rect for a component id. */
  set(id: string, rect: CachedRect): void {
    this.slots.set(id, { rect, stamp: this.stamp })
  }

  /** The last measured rect for an id, or undefined when never measured. */
  get(id: string): CachedRect | undefined {
    return this.slots.get(id)?.rect
  }

  has(id: string): boolean {
    return this.slots.has(id)
  }

  /** The invalidation stamp a cached rect was measured against. */
  stampOf(id: string): number | undefined {
    return this.slots.get(id)?.stamp
  }

  /** The cache's current invalidation stamp. */
  currentStamp(): number {
    return this.stamp
  }

  /**
   * Whether a cached rect predates the latest invalidation and should be
   * re-measured. Unknown ids are always stale.
   */
  isStale(id: string): boolean {
    const slot = this.slots.get(id)
    return slot === undefined || slot.stamp < this.stamp
  }

  /**
   * Mark cached rects stale. Bumping the stamp is enough to make isStale()
   * return true for everything measured before now; the rects stay readable
   * until they are re-measured so overlays don't flicker to zero mid-refresh.
   */
  invalidate(id?: string): void {
    this.stamp += 1
    if (id !== undefined) {
      this.slots.delete(id)
    }
  }

  /** Forget a single entry entirely (component unregistered). */
  forget(id: string): void {
    this.slots.delete(id)
  }

  /** Drop everything (host torn down / replaced). */
  clear(): void {
    this.slots.clear()
    this.stamp += 1
  }
}
