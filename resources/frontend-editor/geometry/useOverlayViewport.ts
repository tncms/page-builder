// HOTFIX — the overlay viewport host tracker.
//
// The `.pb-fe-overlay-viewport` host is positioned exactly over the iframe's
// visible rectangle so that overlays rendered inside it (a) can use iframe-
// viewport rects verbatim as host-local coordinates and (b) are clipped to the
// preview by `overflow: hidden`, so a margin box or handle can never paint under
// the sidebar. This composable is the SINGLE place the iframe's screen offset is
// read; it keeps the host rect fresh as the frame moves or resizes.
//
// It recomputes (rAF-coalesced) on:
//   - window scroll (capture: true — also catches the scrollable canvas)
//   - window resize
//   - any ResizeObserver target (the iframe itself and the canvas around it can
//     change size without a scroll event — sidebar collapse, device-mode switch)
// `onChange` fires after each real change so the caller can re-request the
// iframe's component geometry (belt-and-suspenders for a frame that MOVES without
// resizing, which no observer would otherwise catch).

import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue'
import { frameRect, type Rect } from './IframeCoordinates'

export interface OverlayViewport {
  /** The iframe's current screen rect — apply as the host's fixed position. */
  rect: Ref<Rect>
  /** Bumps on every real rect change (lets overlays force a recompute). */
  revision: Ref<number>
  /** Schedule a coalesced recompute (safe to call repeatedly). */
  refresh: () => void
}

function sameRect(a: Rect, b: Rect): boolean {
  return a.top === b.top && a.left === b.left && a.width === b.width && a.height === b.height
}

export function useOverlayViewport(
  frameEl: Ref<HTMLIFrameElement | null>,
  observed: () => Array<Element | null | undefined>,
  onChange?: () => void,
): OverlayViewport {
  const rect = ref<Rect>({ top: 0, left: 0, width: 0, height: 0 })
  const revision = ref(0)
  let raf = 0
  let ro: ResizeObserver | null = null

  function apply(): void {
    raf = 0
    const next = frameRect(frameEl.value)
    if (sameRect(next, rect.value)) return
    rect.value = next
    revision.value += 1
    onChange?.()
  }

  function refresh(): void {
    if (raf) return
    raf = requestAnimationFrame(apply)
  }

  const onScroll = (): void => refresh()
  const onResize = (): void => refresh()

  onMounted(() => {
    // capture: true so scrolls inside the overflow:auto canvas (which don't bubble
    // to window) still fire this — otherwise the host would drift on canvas scroll.
    window.addEventListener('scroll', onScroll, { capture: true, passive: true })
    window.addEventListener('resize', onResize)
    if (typeof ResizeObserver !== 'undefined') {
      ro = new ResizeObserver(() => refresh())
      for (const el of observed()) {
        if (el) ro.observe(el)
      }
    }
    refresh()
  })

  onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll, { capture: true } as EventListenerOptions)
    window.removeEventListener('resize', onResize)
    if (raf) cancelAnimationFrame(raf)
    ro?.disconnect()
    ro = null
  })

  return { rect, revision, refresh }
}
