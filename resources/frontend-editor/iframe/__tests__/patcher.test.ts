import { describe, it, expect, beforeEach } from 'vitest'
import { DomPatcher } from '../patcher'

function setup(): HTMLElement {
  document.body.innerHTML = `
    <div id="pb-frontend-canvas" data-pb-frontend-canvas>
      <div class="pb-editor-render-marker" data-pb-component-id="cta-1" data-pb-component-kind="section" data-pb-component-type="cta">
        <section class="pb-section pb-cta">
          <h2 class="pb-cta__title">Old title</h2>
          <p class="pb-cta__text">Old text</p>
          <a class="pb-cta__button" href="/old">Go</a>
        </section>
      </div>
    </div>`
  return document.getElementById('pb-frontend-canvas') as HTMLElement
}

describe('DomPatcher', () => {
  let root: HTMLElement
  beforeEach(() => {
    root = setup()
  })

  it('applies a whitelisted style to the component root and ignores others', () => {
    const patcher = new DomPatcher(root)
    patcher.patchStyle('cta-1', null, { textAlign: 'center', evilProp: 'boom' })

    const section = root.querySelector('.pb-cta') as HTMLElement
    expect(section.style.textAlign).toBe('center')
    expect(section.getAttribute('style')).not.toContain('boom')
  })

  it('patches text and attributes on selector-scoped children', () => {
    const patcher = new DomPatcher(root)
    patcher.patchText('cta-1', '.pb-cta__title', 'New title')
    patcher.patchAttr('cta-1', '.pb-cta__button', 'href', '/new')

    expect((root.querySelector('.pb-cta__title') as HTMLElement).textContent).toBe('New title')
    expect((root.querySelector('.pb-cta__button') as HTMLElement).getAttribute('href')).toBe('/new')
  })

  it('preserves newlines as <br> in text patches', () => {
    const patcher = new DomPatcher(root)
    patcher.patchText('cta-1', '.pb-cta__text', 'line1\nline2')
    expect((root.querySelector('.pb-cta__text') as HTMLElement).innerHTML).toBe('line1<br>line2')
  })

  it('clear() reverts every inline patch to the original', () => {
    const patcher = new DomPatcher(root)
    patcher.patchStyle('cta-1', null, { textAlign: 'right' })
    patcher.patchText('cta-1', '.pb-cta__title', 'Changed')
    patcher.patchAttr('cta-1', '.pb-cta__button', 'href', '/changed')

    patcher.clear('cta-1')

    const section = root.querySelector('.pb-cta') as HTMLElement
    expect(section.style.textAlign).toBe('')
    expect((root.querySelector('.pb-cta__title') as HTMLElement).textContent).toBe('Old title')
    expect((root.querySelector('.pb-cta__button') as HTMLElement).getAttribute('href')).toBe('/old')
  })

  it('reset() drops tracking so a later clear is a no-op', () => {
    const patcher = new DomPatcher(root)
    patcher.patchText('cta-1', '.pb-cta__title', 'Changed')
    patcher.reset()
    patcher.clear('cta-1')
    // The change stays because tracking was dropped (elements were "swapped").
    expect((root.querySelector('.pb-cta__title') as HTMLElement).textContent).toBe('Changed')
  })

  it('does nothing for an unknown component id', () => {
    const patcher = new DomPatcher(root)
    expect(() => patcher.patchStyle('missing', null, { textAlign: 'left' })).not.toThrow()
  })
})
