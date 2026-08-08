// Phase 13 — Geometry observer.
//
// Keeps geometry fresh without polling. A ResizeObserver catches the canvas and
// its content changing size (viewport resize, images loading, responsive
// breakpoints); a MutationObserver catches the preview HTML being swapped in
// after a re-render. Both simply notify — they never measure — so the owner
// (OverlayManager) can coalesce notifications into a single rAF refresh.

export interface GeometryObserverHost {
  onChange: () => void
}

// Narrow the global constructors so the observer degrades gracefully in
// environments (or test doms) that don't implement them.
type ResizeObserverCtor = typeof ResizeObserver
type MutationObserverCtor = typeof MutationObserver

export class GeometryObserver {
  private resizeObserver: ResizeObserver | null = null
  private mutationObserver: MutationObserver | null = null
  private host: HTMLElement | null = null

  constructor(private readonly onChange: () => void) {}

  /** Begin observing `host` (the preview content) for size + DOM changes. */
  observe(host: HTMLElement): void {
    this.disconnect()
    this.host = host

    const Resize = (globalThis as { ResizeObserver?: ResizeObserverCtor }).ResizeObserver
    if (Resize) {
      this.resizeObserver = new Resize(() => this.onChange())
      this.resizeObserver.observe(host)
    }

    const Mutation = (globalThis as { MutationObserver?: MutationObserverCtor }).MutationObserver
    if (Mutation) {
      this.mutationObserver = new Mutation(() => this.onChange())
      this.mutationObserver.observe(host, { childList: true, subtree: true, attributes: true })
    }
  }

  /** Also watch a second element's size (e.g. the scroll viewport). */
  observeSize(el: HTMLElement): void {
    this.resizeObserver?.observe(el)
  }

  disconnect(): void {
    this.resizeObserver?.disconnect()
    this.mutationObserver?.disconnect()
    this.resizeObserver = null
    this.mutationObserver = null
    this.host = null
  }

  observedHost(): HTMLElement | null {
    return this.host
  }
}
