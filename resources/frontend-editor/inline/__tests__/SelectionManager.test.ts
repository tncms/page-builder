import { describe, it, expect, beforeEach } from 'vitest'
import { resolveInlineTarget, readText } from '../SelectionManager'
import { SHELL_TO_IFRAME, IFRAME_TO_SHELL } from '../../bridge/messages'

function setup(): HTMLElement {
  document.body.innerHTML = `
    <div id="pb-frontend-canvas" data-pb-frontend-canvas>
      <div class="pb-editor-render-marker" data-pb-component-id="cta-1" data-pb-component-kind="section" data-pb-component-type="cta">
        <section class="pb-cta">
          <h2 class="pb-cta__title">Hello</h2>
          <p class="pb-cta__text">a<br>b</p>
        </section>
      </div>
    </div>`
  return document.getElementById('pb-frontend-canvas') as HTMLElement
}

describe('SelectionManager', () => {
  let canvas: HTMLElement
  beforeEach(() => {
    canvas = setup()
  })

  it('resolves an inline target descriptor for an editable field', () => {
    const title = canvas.querySelector('.pb-cta__title') as HTMLElement
    const found = resolveInlineTarget(title, canvas)
    expect(found).not.toBeNull()
    expect(found?.descriptor).toMatchObject({
      componentId: 'cta-1',
      type: 'cta',
      field: 'title',
      selector: '.pb-cta__title',
      multiline: false,
      translatable: true,
      value: 'Hello',
    })
    expect(found?.descriptor.typography).toHaveProperty('fontSize')
    expect(found?.el).toBe(title)
  })

  it('restores <br> to newlines when reading editable text', () => {
    const text = canvas.querySelector('.pb-cta__text') as HTMLElement
    expect(readText(text)).toBe('a\nb')
    expect(resolveInlineTarget(text, canvas)?.descriptor.value).toBe('a\nb')
  })

  it('returns null for a non-editable target', () => {
    const section = canvas.querySelector('.pb-cta') as HTMLElement
    expect(resolveInlineTarget(section, canvas)).toBeNull()
  })
})

describe('inline message types are on the allow-lists', () => {
  it('registers inline-start/inline-rect (iframe->shell) and inline-end (shell->iframe)', () => {
    expect(IFRAME_TO_SHELL).toContain('pb:inline-start')
    expect(IFRAME_TO_SHELL).toContain('pb:inline-rect')
    expect(SHELL_TO_IFRAME).toContain('pb:inline-end')
  })
})
