import { describe, it, expect } from 'vitest'
import { measureElement, parsePx } from '../measure'
import { STYLE_WHITELIST } from '../../iframe/patchMap'
import { IFRAME_TO_SHELL } from '../../bridge/messages'

describe('parsePx', () => {
  it('parses px strings and defaults to 0', () => {
    expect(parsePx('12px')).toBe(12)
    expect(parsePx('0px')).toBe(0)
    expect(parsePx('')).toBe(0)
    expect(parsePx('auto')).toBe(0)
  })
})

describe('measureElement', () => {
  it('reads padding/margin longhands into numeric sides', () => {
    const el = document.createElement('div')
    el.style.paddingTop = '10px'
    el.style.paddingLeft = '8px'
    el.style.marginBottom = '20px'
    document.body.appendChild(el)

    const m = measureElement('c1', el)
    expect(m.componentId).toBe('c1')
    expect(m.padding.top).toBe(10)
    expect(m.padding.left).toBe(8)
    expect(m.margin.bottom).toBe(20)
    expect(m.rect).toHaveProperty('width')
    expect(typeof m.border.top).toBe('number')
  })
})

describe('Phase 19 wiring', () => {
  it('adds per-side spacing props to the style whitelist', () => {
    for (const prop of ['paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'marginTop', 'marginRight', 'marginBottom', 'marginLeft']) {
      expect(STYLE_WHITELIST).toContain(prop)
    }
  })

  it('registers pb:box-model on the iframe->shell channel', () => {
    expect(IFRAME_TO_SHELL).toContain('pb:box-model')
  })
})
