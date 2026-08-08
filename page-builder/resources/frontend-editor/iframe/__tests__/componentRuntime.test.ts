import { describe, it, expect, beforeEach } from 'vitest'
import { replaceComponent } from '../componentRuntime'

const marker = (id: string, inner: string, type = 'hero', kind = 'section') =>
  `<div class="pb-editor-render-marker" data-pb-component-id="${id}" ` +
  `data-pb-component-kind="${kind}" data-pb-component-type="${type}">${inner}</div>`

function setup(): HTMLElement {
  document.body.innerHTML = `<div id="pb-frontend-canvas">${marker('sec-1', '<h1 id="t">Old</h1>')}</div>`
  return document.getElementById('pb-frontend-canvas') as HTMLElement
}

describe('componentRuntime.replaceComponent (Phase 21)', () => {
  let canvas: HTMLElement
  beforeEach(() => {
    canvas = setup()
  })

  it('replaces the target marker outerHTML in place', () => {
    const ok = replaceComponent(canvas, 'sec-1', marker('sec-1', '<h1 id="t">New</h1>'))

    expect(ok).toBe(true)
    // The old text is gone; the new one is present under the same id.
    expect(canvas.querySelector('#t')?.textContent).toBe('New')
    const el = canvas.querySelector('[data-pb-component-id="sec-1"]')
    expect(el).not.toBeNull()
    // Exactly one marker for the id — the node was replaced, not appended.
    expect(canvas.querySelectorAll('[data-pb-component-id="sec-1"]').length).toBe(1)
  })

  it('returns false and changes nothing when the marker is missing', () => {
    const before = canvas.innerHTML
    const ok = replaceComponent(canvas, 'does-not-exist', marker('does-not-exist', '<p>x</p>'))

    expect(ok).toBe(false)
    expect(canvas.innerHTML).toBe(before)
  })

  it('never touches a same-id marker that lives outside the canvas', () => {
    // A marker with the SAME id sits outside the canvas; the canvas has none.
    const outside = document.createElement('div')
    outside.innerHTML = marker('ghost', '<p id="out">outside</p>')
    document.body.appendChild(outside)

    const ok = replaceComponent(canvas, 'ghost', marker('ghost', '<p>hacked</p>'))

    expect(ok).toBe(false)
    // The outside element is untouched — replacement is scoped to the canvas.
    expect(document.getElementById('out')?.textContent).toBe('outside')
  })

  it('strips <script> from replacement HTML (no eval)', () => {
    const ok = replaceComponent(
      canvas,
      'sec-1',
      marker('sec-1', '<h1 id="t">Safe</h1><script>window.__pwned = true</script>'),
    )

    expect(ok).toBe(true)
    expect(canvas.querySelector('script')).toBeNull()
    expect((window as unknown as { __pwned?: boolean }).__pwned).toBeUndefined()
  })

  it('rejects HTML whose root is not a component marker', () => {
    const before = canvas.innerHTML
    const ok = replaceComponent(canvas, 'sec-1', '<div class="not-a-marker">nope</div>')

    expect(ok).toBe(false)
    expect(canvas.innerHTML).toBe(before)
  })
})
