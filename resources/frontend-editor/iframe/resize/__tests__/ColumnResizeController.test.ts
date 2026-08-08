import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { ColumnResizeController } from '../ColumnResizeController'

interface Rect {
  top: number
  left: number
  width: number
  height: number
}

function stubRect(el: HTMLElement, r: Rect): void {
  el.getBoundingClientRect = () =>
    ({
      top: r.top,
      left: r.left,
      width: r.width,
      height: r.height,
      right: r.left + r.width,
      bottom: r.top + r.height,
      x: r.left,
      y: r.top,
      toJSON() {},
    }) as DOMRect
}

function marker(id: string, type: string, kind = 'block'): HTMLElement {
  const el = document.createElement('div')
  el.setAttribute('data-pb-component-id', id)
  el.setAttribute('data-pb-component-kind', kind)
  el.setAttribute('data-pb-component-type', type)
  return el
}

/**
 * Build a container marker whose inner flex-row holds `widths` column markers.
 * Column rects are laid out left-to-right so the controller can measure them.
 * Returns the canvas plus the container id and column ids.
 */
function buildContainer(widths: number[], rowWidth = 600, containerId = 'cont'): HTMLElement {
  const canvas = document.createElement('div')
  canvas.id = 'pb-frontend-canvas'

  const container = marker(containerId, 'container', 'block')
  const row = document.createElement('div')
  row.className = 'pb-container'
  container.appendChild(row)

  let left = 0
  widths.forEach((pct, i) => {
    const px = (pct / 100) * rowWidth
    const col = marker(`col${i + 1}`, 'column', 'block')
    stubRect(col, { top: 100, left, width: px, height: 200 })
    row.appendChild(col)
    left += px
  })

  canvas.appendChild(container)
  document.body.appendChild(canvas)
  return canvas
}

function handles(): HTMLElement[] {
  return Array.from(document.querySelectorAll<HTMLElement>('.pb-fe-col-resize'))
}

function pointer(type: string, init: Partial<PointerEvent> = {}): Event {
  return new MouseEvent(type, { bubbles: true, ...(init as MouseEventInit) })
}

beforeEach(() => {
  vi.stubGlobal(
    'requestAnimationFrame',
    (cb: FrameRequestCallback) => setTimeout(() => cb(0), 0) as unknown as number,
  )
  vi.stubGlobal('cancelAnimationFrame', (h: number) => clearTimeout(h))
})

afterEach(() => {
  document.body.innerHTML = ''
  vi.unstubAllGlobals()
  vi.useRealTimers()
})

describe('ColumnResizeController — handle lifecycle', () => {
  it('shows one handle per divider when a container is selected', () => {
    const canvas = buildContainer([50, 50])
    const c = new ColumnResizeController(canvas, vi.fn())
    c.sync('cont', null)
    expect(handles()).toHaveLength(1)
  })

  it('shows handles for 2–6 columns (Part 11)', () => {
    for (const n of [2, 3, 4, 5, 6]) {
      const canvas = buildContainer(new Array(n).fill(100 / n))
      const c = new ColumnResizeController(canvas, vi.fn())
      c.sync('cont', null)
      expect(handles()).toHaveLength(n - 1)
      c.destroy()
      document.body.innerHTML = ''
    }
  })

  it('resolves a selected column up to its container', () => {
    const canvas = buildContainer([50, 50])
    const c = new ColumnResizeController(canvas, vi.fn())
    c.sync('col1', null)
    expect(handles()).toHaveLength(1)
  })

  it('shows nothing for a non-column selection', () => {
    const canvas = buildContainer([50, 50])
    canvas.appendChild(marker('lonely', 'hero', 'section'))
    const c = new ColumnResizeController(canvas, vi.fn())
    c.sync('lonely', null)
    expect(handles()).toHaveLength(0)
  })

  it('marks handles up for accessibility (Part 15)', () => {
    const canvas = buildContainer([30, 70])
    const c = new ColumnResizeController(canvas, vi.fn())
    c.sync('cont', null)
    const h = handles()[0]
    expect(h.getAttribute('role')).toBe('separator')
    expect(h.getAttribute('aria-orientation')).toBe('vertical')
    expect(h.getAttribute('tabindex')).toBe('0')
    expect(h.getAttribute('aria-valuemin')).toBe('10')
  })
})

describe('ColumnResizeController — drag commit', () => {
  it('commits exactly one apply-column-layout on release (Part 3/14)', () => {
    const commit = vi.fn()
    const canvas = buildContainer([50, 50], 600)
    const c = new ColumnResizeController(canvas, commit)
    c.sync('cont', null)

    const h = handles()[0]
    h.dispatchEvent(pointer('pointerdown', { clientX: 300, clientY: 150 }))
    // Drag right by 120px of a 600px row => +20% (snap off via Shift for exactness).
    document.dispatchEvent(pointer('pointermove', { clientX: 420, clientY: 150, shiftKey: true }))
    document.dispatchEvent(pointer('pointerup', { clientX: 420, clientY: 150, shiftKey: true }))

    expect(commit).toHaveBeenCalledTimes(1)
    expect(commit).toHaveBeenCalledWith('cont', ['70', '30'])

    // Live patch (Part 13): the committed widths are reflected inline on the
    // column markers (flex-basis/max-width/width) so there is no flash before the
    // authoritative re-render lands.
    const col1 = canvas.querySelector<HTMLElement>('[data-pb-component-id="col1"]')!
    expect(col1.style.getPropertyValue('flex-basis')).toBe('70%')
    expect(col1.style.getPropertyValue('max-width')).toBe('70%')
  })

  it('snaps to the 5% grid by default (Part 5)', () => {
    const commit = vi.fn()
    const canvas = buildContainer([50, 50], 600)
    const c = new ColumnResizeController(canvas, commit)
    c.sync('cont', null)
    const h = handles()[0]
    h.dispatchEvent(pointer('pointerdown', { clientX: 300, clientY: 150 }))
    // +18px => +3% raw; snapped to nearest 5% grid from 50 => 55.
    document.dispatchEvent(pointer('pointermove', { clientX: 318, clientY: 150 }))
    document.dispatchEvent(pointer('pointerup', { clientX: 318, clientY: 150 }))
    expect(commit).toHaveBeenCalledWith('cont', ['55', '45'])
  })

  it('respects minimum width (Part 6)', () => {
    const commit = vi.fn()
    const canvas = buildContainer([50, 50], 600)
    const c = new ColumnResizeController(canvas, commit)
    c.sync('cont', null)
    const h = handles()[0]
    h.dispatchEvent(pointer('pointerdown', { clientX: 300, clientY: 150 }))
    document.dispatchEvent(pointer('pointermove', { clientX: 900, clientY: 150, shiftKey: true }))
    document.dispatchEvent(pointer('pointerup', { clientX: 900, clientY: 150, shiftKey: true }))
    expect(commit).toHaveBeenCalledWith('cont', ['90', '10'])
  })

  it('Escape cancels without committing and restores widths (Part 19)', () => {
    const commit = vi.fn()
    const canvas = buildContainer([50, 50], 600)
    const c = new ColumnResizeController(canvas, commit)
    c.sync('cont', null)
    const h = handles()[0]
    const col1 = canvas.querySelector<HTMLElement>('[data-pb-component-id="col1"]')!

    h.dispatchEvent(pointer('pointerdown', { clientX: 300, clientY: 150 }))
    document.dispatchEvent(pointer('pointermove', { clientX: 420, clientY: 150, shiftKey: true }))
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))

    expect(commit).not.toHaveBeenCalled()
    expect(col1.style.getPropertyValue('flex-basis')).toBe('')
  })

  it('pointercancel aborts the drag (Part 19)', () => {
    const commit = vi.fn()
    const canvas = buildContainer([50, 50], 600)
    const c = new ColumnResizeController(canvas, commit)
    c.sync('cont', null)
    const h = handles()[0]
    h.dispatchEvent(pointer('pointerdown', { clientX: 300, clientY: 150 }))
    document.dispatchEvent(pointer('pointermove', { clientX: 420, clientY: 150 }))
    document.dispatchEvent(pointer('pointercancel', {}))
    expect(commit).not.toHaveBeenCalled()
    expect(c.dragging).toBe(false)
  })
})

describe('ColumnResizeController — double click + keyboard', () => {
  it('double-clicking a divider equalises the columns (Part 7)', () => {
    const commit = vi.fn()
    const canvas = buildContainer([20, 40, 40], 600)
    const c = new ColumnResizeController(canvas, commit)
    c.sync('cont', null)
    handles()[0].dispatchEvent(pointer('dblclick', { clientX: 200, clientY: 150 }))
    expect(commit).toHaveBeenCalledWith('cont', ['33.3334', '33.3333', '33.3333'])
  })

  it('ArrowRight nudges by the snap step and commits one command (Part 15)', () => {
    const commit = vi.fn()
    const canvas = buildContainer([50, 50], 600)
    const c = new ColumnResizeController(canvas, commit)
    c.sync('cont', null)
    handles()[0].dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }))
    expect(commit).toHaveBeenCalledWith('cont', ['55', '45'])
  })

  it('Shift+Arrow uses a fine 1% step', () => {
    const commit = vi.fn()
    const canvas = buildContainer([50, 50], 600)
    const c = new ColumnResizeController(canvas, commit)
    c.sync('cont', null)
    handles()[0].dispatchEvent(
      new KeyboardEvent('keydown', { key: 'ArrowLeft', shiftKey: true, bubbles: true }),
    )
    expect(commit).toHaveBeenCalledWith('cont', ['49', '51'])
  })
})

describe('ColumnResizeController — nested containers (Part 12)', () => {
  it('resizes only the selected container, never a nested one', () => {
    const canvas = buildContainer([50, 50], 600, 'outer')
    // Nest a container inside the first column with its own two columns.
    const col1 = canvas.querySelector<HTMLElement>('[data-pb-component-id="col1"]')!
    const inner = marker('inner', 'container', 'block')
    const innerRow = document.createElement('div')
    inner.appendChild(innerRow)
    ;['n1', 'n2'].forEach((id, i) => {
      const nc = marker(id, 'column', 'block')
      stubRect(nc, { top: 100, left: i * 100, width: 100, height: 100 })
      innerRow.appendChild(nc)
    })
    col1.appendChild(inner)

    const commit = vi.fn()
    const c = new ColumnResizeController(canvas, commit)

    // Selecting the OUTER container shows only its two columns' one divider.
    c.sync('outer', null)
    expect(handles()).toHaveLength(1)

    // Selecting the INNER container targets only its columns.
    c.sync('inner', null)
    expect(handles()).toHaveLength(1)
    handles()[0].dispatchEvent(pointer('dblclick', { clientX: 50, clientY: 150 }))
    expect(commit).toHaveBeenCalledWith('inner', ['50', '50'])
  })
})
