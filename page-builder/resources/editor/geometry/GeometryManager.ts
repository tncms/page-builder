// Phase 13 — Geometry manager.
//
// The single source of truth for where every rendered component sits in the
// editor. The visual canvas renders real Blade HTML (each node wrapped in a
// `[data-pb-component-id]` marker); this manager registers those markers,
// measures them once per refresh, and exposes their rectangles in coordinates
// local to the canvas viewport. Selection, hover, labels, toolbar — and later
// drag, resize, snapping and guides — all read from here and never touch the
// DOM themselves.

import { GeometryCache, type CachedRect } from './GeometryCache'

const MARKER_ATTR = 'data-pb-component-id'
const MARKER_SELECTOR = `[${MARKER_ATTR}]`

export interface GeometryEntry {
  id: string
  element: HTMLElement
  kind: string
  type: string
  parentId: string | null
  depth: number
  visible: boolean
}

/** A component rectangle in coordinates local to the canvas viewport origin. */
export interface GeometryRect {
  id: string
  x: number
  y: number
  width: number
  height: number
  top: number
  left: number
  right: number
  bottom: number
}

/** Measures an element's box. Injectable so tests can supply fake layout. */
export type Measurer = (el: HTMLElement) => CachedRect

const defaultMeasurer: Measurer = (el) => el.getBoundingClientRect()

const ZERO_RECT: CachedRect = { x: 0, y: 0, width: 0, height: 0, top: 0, left: 0, right: 0, bottom: 0 }

export class GeometryManager {
  private entries = new Map<string, GeometryEntry>()
  private cache = new GeometryCache()
  private viewport: HTMLElement | null = null
  private viewportRect: CachedRect = ZERO_RECT
  private measure: Measurer
  private version = 0

  constructor(measure: Measurer = defaultMeasurer) {
    this.measure = measure
  }

  /** The scroll container overlays and local rects are measured against. */
  setViewport(el: HTMLElement | null): void {
    this.viewport = el
  }

  /** Swap how boxes are measured (tests inject fake layout). */
  setMeasurer(measure: Measurer): void {
    this.measure = measure
  }

  /** Register a rendered marker element. Idempotent per id. */
  register(element: HTMLElement): GeometryEntry | null {
    const id = element.getAttribute(MARKER_ATTR)
    if (!id) return null

    const entry: GeometryEntry = {
      id,
      element,
      kind: element.getAttribute('data-pb-component-kind') ?? '',
      type: element.getAttribute('data-pb-component-type') ?? '',
      parentId: null,
      depth: 0,
      visible: true,
    }
    this.entries.set(id, entry)
    return entry
  }

  /** Drop a component from the registry and cache. */
  unregister(id: string): void {
    this.entries.delete(id)
    this.cache.forget(id)
  }

  /** Forget every component (host torn down / test reset). */
  clear(): void {
    this.entries.clear()
    this.cache.clear()
  }

  /**
   * Reconcile the registry with the markers currently in `host`. The visual
   * canvas replaces its HTML wholesale on every preview, so ids come and go in
   * bulk; this registers new markers and removes stale ones, then refreshes.
   */
  sync(host: HTMLElement): void {
    const seen = new Set<string>()
    const markers = host.querySelectorAll<HTMLElement>(MARKER_SELECTOR)
    markers.forEach((el) => {
      const id = el.getAttribute(MARKER_ATTR)
      if (!id) return
      seen.add(id)
      const existing = this.entries.get(id)
      if (existing) existing.element = el
      else this.register(el)
    })

    for (const id of [...this.entries.keys()]) {
      if (!seen.has(id)) this.unregister(id)
    }

    this.refresh()
  }

  /**
   * Re-measure the viewport and every registered element, writing rects into
   * the cache. This is the only place layout is read, so callers batch it (one
   * call per animation frame) to avoid layout thrashing.
   */
  refresh(): void {
    this.cache.invalidate()

    this.viewportRect = this.viewport ? this.measure(this.viewport) : ZERO_RECT

    for (const entry of this.entries.values()) {
      const rect = this.measure(entry.element)
      entry.visible = rect.width > 0 && rect.height > 0
      this.cache.set(entry.id, rect)
    }

    this.recomputeHierarchy()
    this.version += 1
  }

  /** Parent/depth derived from DOM ancestry among registered markers. */
  private recomputeHierarchy(): void {
    for (const entry of this.entries.values()) {
      let parentId: string | null = null
      let depth = 0
      let node = entry.element.parentElement
      while (node) {
        const pid = node.getAttribute?.(MARKER_ATTR)
        if (pid && this.entries.has(pid)) {
          if (parentId === null) parentId = pid
          depth += 1
        }
        node = node.parentElement
      }
      entry.parentId = parentId
      entry.depth = depth
    }
  }

  /** The cached viewport (canvas) rect, in viewport coordinates. */
  getViewportRect(): CachedRect {
    return this.viewportRect
  }

  /** A component's rect in coordinates local to the viewport, or null. */
  rect(id: string | null): GeometryRect | null {
    if (!id) return null
    const raw = this.cache.get(id)
    if (!raw) return null
    return this.toLocal(id, raw)
  }

  /** All known component rects (local coordinates), registration order. */
  rects(): GeometryRect[] {
    const out: GeometryRect[] = []
    for (const id of this.entries.keys()) {
      const raw = this.cache.get(id)
      if (raw) out.push(this.toLocal(id, raw))
    }
    return out
  }

  private toLocal(id: string, raw: CachedRect): GeometryRect {
    const left = raw.left - this.viewportRect.left
    const top = raw.top - this.viewportRect.top
    return {
      id,
      x: left,
      y: top,
      left,
      top,
      width: raw.width,
      height: raw.height,
      right: left + raw.width,
      bottom: top + raw.height,
    }
  }

  entry(id: string | null): GeometryEntry | null {
    return id ? this.entries.get(id) ?? null : null
  }

  allEntries(): GeometryEntry[] {
    return [...this.entries.values()]
  }

  has(id: string): boolean {
    return this.entries.has(id)
  }

  count(): number {
    return this.entries.size
  }

  /** Bumped on every refresh so reactive consumers can recompute. */
  currentVersion(): number {
    return this.version
  }

  /** Test/observer seam: the underlying cache. */
  getCache(): GeometryCache {
    return this.cache
  }
}
