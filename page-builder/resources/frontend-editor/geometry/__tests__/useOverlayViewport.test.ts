import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import { useOverlayViewport } from '../useOverlayViewport'

// Run rAF synchronously so a scheduled recompute applies within the test tick.
let rafSpy: ReturnType<typeof vi.spyOn>
beforeEach(() => {
  // Run rAF synchronously and return id 0 so the composable's "already scheduled"
  // guard (which stores the returned id) doesn't block the next refresh in-test.
  rafSpy = vi.spyOn(window, 'requestAnimationFrame').mockImplementation((cb: FrameRequestCallback) => {
    cb(0)
    return 0
  })
})
afterEach(() => rafSpy.mockRestore())

function frame(top: number, left: number, width = 900, height = 600): HTMLIFrameElement {
  const el = document.createElement('iframe')
  el.getBoundingClientRect = () =>
    ({ top, left, width, height, right: left + width, bottom: top + height, x: left, y: top, toJSON() {} }) as DOMRect
  return el
}

function harness(frameEl: HTMLIFrameElement, onChange?: () => void) {
  const state: { rect?: unknown; revision?: number } = {}
  const Comp = defineComponent({
    setup() {
      const el = ref<HTMLIFrameElement | null>(frameEl)
      const vp = useOverlayViewport(el, () => [el.value], onChange)
      state.rect = vp.rect
      // Expose the reactive refs for assertions.
      Object.defineProperty(state, 'revision', { get: () => vp.revision.value, configurable: true })
      Object.defineProperty(state, 'current', { get: () => vp.rect.value, configurable: true })
      return () => h('div')
    },
  })
  const wrapper = mount(Comp)
  return { wrapper, state: state as { current: { top: number; left: number; width: number; height: number }; revision: number } }
}

describe('useOverlayViewport', () => {
  it('tracks the iframe screen rect on mount (single conversion point)', () => {
    const { state } = harness(frame(100, 320))
    expect(state.current).toEqual({ top: 100, left: 320, width: 900, height: 600 })
  })

  it('recomputes and bumps revision on a window resize event', async () => {
    const el = frame(100, 320)
    const onChange = vi.fn()
    const { state } = harness(el, onChange)
    const before = state.revision
    const changesBefore = onChange.mock.calls.length

    // The iframe moved/resized (e.g. sidebar collapse); a resize event fires.
    el.getBoundingClientRect = () =>
      ({ top: 100, left: 260, width: 960, height: 600, right: 1220, bottom: 700, x: 260, y: 100, toJSON() {} }) as DOMRect
    window.dispatchEvent(new Event('resize'))

    expect(state.current.left).toBe(260)
    expect(state.current.width).toBe(960)
    expect(state.revision).toBe(before + 1)
    expect(onChange.mock.calls.length).toBe(changesBefore + 1)
  })

  it('does not bump when the rect is unchanged (no spurious refresh)', () => {
    const { state } = harness(frame(100, 320))
    const before = state.revision
    window.dispatchEvent(new Event('resize'))
    expect(state.revision).toBe(before)
  })
})
