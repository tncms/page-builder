import { describe, it, expect, afterEach } from 'vitest'
import { installCanvasNavigationGuard, CANVAS_NONINTERACTIVE_CLASS } from '../canvasGuard'

// LT-M1 Increment 2D — the editor canvas must not behave like an interactive
// website: menu / logo / language / CTA links and form posts are neutralised,
// while Page Builder interactions (which never call the default browser action)
// keep flowing because the guard never stops propagation.

let dispose: (() => void) | null = null

afterEach(() => {
  dispose?.()
  dispose = null
  document.body.innerHTML = ''
})

function clickOn(el: Element): MouseEvent {
  const event = new MouseEvent('click', { bubbles: true, cancelable: true })
  el.dispatchEvent(event)
  return event
}

describe('installCanvasNavigationGuard', () => {
  it('cancels navigation for an anchor click (menu / logo / language / CTA)', () => {
    document.body.innerHTML = '<nav><a href="/features" id="menu">Features</a></nav>'
    dispose = installCanvasNavigationGuard(document)

    const event = clickOn(document.getElementById('menu')!)

    expect(event.defaultPrevented).toBe(true)
  })

  it('cancels middle/modified-click link opens (auxclick)', () => {
    document.body.innerHTML = '<a href="/vi" id="lang">VI</a>'
    dispose = installCanvasNavigationGuard(document)

    const event = new MouseEvent('auxclick', { bubbles: true, cancelable: true })
    document.getElementById('lang')!.dispatchEvent(event)

    expect(event.defaultPrevented).toBe(true)
  })

  it('cancels form submissions', () => {
    document.body.innerHTML = '<form id="f"><input name="q" /></form>'
    dispose = installCanvasNavigationGuard(document)

    const event = new Event('submit', { bubbles: true, cancelable: true })
    document.getElementById('f')!.dispatchEvent(event)

    expect(event.defaultPrevented).toBe(true)
  })

  it('does NOT cancel a click that is not a link (Page Builder marker / overlay)', () => {
    document.body.innerHTML = '<div id="pb-frontend-canvas"><div data-pb-component-id="pb-hero" id="marker">Hero</div></div>'
    dispose = installCanvasNavigationGuard(document)

    const event = clickOn(document.getElementById('marker')!)

    expect(event.defaultPrevented).toBe(false)
  })

  it('cancels navigation but still lets the event propagate (selection keeps working)', () => {
    document.body.innerHTML =
      '<div id="pb-frontend-canvas"><div data-pb-component-id="pb-cta"><a href="/signup" id="cta">Sign up</a></div></div>'
    dispose = installCanvasNavigationGuard(document)

    // A canvas-style bubble listener (mirrors main.ts selection wiring).
    let selected = false
    document.getElementById('pb-frontend-canvas')!.addEventListener('click', () => {
      selected = true
    })

    const event = clickOn(document.getElementById('cta')!)

    expect(event.defaultPrevented).toBe(true) // navigation cancelled
    expect(selected).toBe(true) // selection still received the click
  })

  it('stops guarding once disposed', () => {
    document.body.innerHTML = '<a href="/home" id="home">Home</a>'
    dispose = installCanvasNavigationGuard(document)
    dispose()
    dispose = null

    const event = clickOn(document.getElementById('home')!)

    expect(event.defaultPrevented).toBe(false)
  })

  it('marks the document non-interactive on install and clears it on dispose', () => {
    const disposer = installCanvasNavigationGuard(document)
    expect(document.documentElement.classList.contains(CANVAS_NONINTERACTIVE_CLASS)).toBe(true)

    disposer()
    expect(document.documentElement.classList.contains(CANVAS_NONINTERACTIVE_CLASS)).toBe(false)
  })
})
