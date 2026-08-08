// Phase 14 — Auto-scroll.
//
// While dragging near a viewport edge, the canvas should scroll so the drop
// target can be off-screen at pickup. This computes a scroll velocity from how
// deep the pointer is inside an edge band and applies it on an animation frame
// loop until the pointer leaves the band or the drag ends. The velocity math is
// a pure function so it can be unit-tested without a real scroll container.

export interface AutoScrollThresholds {
  /** Width of the edge band, in px, that triggers scrolling. */
  edge: number
  /** Maximum scroll speed, in px per frame, at the very edge. */
  maxSpeed: number
}

export interface ScrollVelocity {
  vx: number
  vy: number
}

const DEFAULTS: AutoScrollThresholds = { edge: 60, maxSpeed: 18 }

type RafFn = (cb: () => void) => number
type CancelRafFn = (handle: number) => void

export class AutoScroll {
  private readonly thresholds: AutoScrollThresholds
  private viewport: HTMLElement | null = null
  private velocity: ScrollVelocity = { vx: 0, vy: 0 }
  private frame: number | null = null
  private readonly raf: RafFn
  private readonly cancelRaf: CancelRafFn

  constructor(thresholds: Partial<AutoScrollThresholds> = {}) {
    this.thresholds = { ...DEFAULTS, ...thresholds }
    const g = globalThis as { requestAnimationFrame?: RafFn; cancelAnimationFrame?: CancelRafFn }
    this.raf = g.requestAnimationFrame?.bind(globalThis) ?? ((cb) => {
      Promise.resolve().then(cb)
      return 0
    })
    this.cancelRaf = g.cancelAnimationFrame?.bind(globalThis) ?? (() => {})
  }

  /**
   * Scroll velocity for a pointer at (clientX, clientY) over a viewport rect.
   * Ramps linearly from 0 at the band's inner edge to maxSpeed at the border.
   */
  velocityFor(rect: { top: number; bottom: number; left: number; right: number }, x: number, y: number): ScrollVelocity {
    const { edge, maxSpeed } = this.thresholds
    const ramp = (distance: number): number => {
      if (distance >= edge) return 0
      const depth = Math.max(0, edge - distance) / edge
      return depth * maxSpeed
    }
    const vy = ramp(y - rect.top) > 0 ? -ramp(y - rect.top) : ramp(rect.bottom - y)
    const vx = ramp(x - rect.left) > 0 ? -ramp(x - rect.left) : ramp(rect.right - x)
    return { vx, vy }
  }

  /** Update the desired velocity from the current pointer and keep the loop alive. */
  update(viewport: HTMLElement, clientX: number, clientY: number): void {
    this.viewport = viewport
    this.velocity = this.velocityFor(viewport.getBoundingClientRect(), clientX, clientY)
    if (this.velocity.vx !== 0 || this.velocity.vy !== 0) this.ensureLoop()
  }

  /** Stop scrolling and cancel the loop. */
  stop(): void {
    this.velocity = { vx: 0, vy: 0 }
    if (this.frame !== null) {
      this.cancelRaf(this.frame)
      this.frame = null
    }
    this.viewport = null
  }

  private ensureLoop(): void {
    if (this.frame !== null) return
    const tick = (): void => {
      this.frame = null
      const vp = this.viewport
      if (!vp || (this.velocity.vx === 0 && this.velocity.vy === 0)) return
      if (this.velocity.vy !== 0) vp.scrollTop += this.velocity.vy
      if (this.velocity.vx !== 0) vp.scrollLeft += this.velocity.vx
      this.frame = this.raf(tick)
    }
    this.frame = this.raf(tick)
  }
}
