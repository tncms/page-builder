// PB-TEMPLATE-REGISTRY-2-H1 — layer-ownership contract for the Resource Browser modal.
//
// The accepted R2 Resource Browser tied the legacy picker toolbar's raw z-index (60)
// in the Structure Editor, and sat far below the in-canvas component toolbar
// (2147483000) in the Frontend/Live Editor. This locks the semantic-layer authority
// so the modal deliberately outranks both editor-chrome surfaces, the nested confirm
// stays above the modal, and a genuinely top-priority dialog (Save as Design) stays
// above the modal — all through named tokens, never raw magic integers.
import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const read = (rel: string): string => readFileSync(resolve(here, rel), 'utf8')

const tokens = read('../../../shared-ui/tokens.css')
const resourceBrowser = read('../ResourceBrowser.vue')
const templateConfirm = read('../TemplateConfirmDialog.vue')
const componentToolbar = read('../../../frontend-editor/shell/components/toolbar/toolbar.css')
const saveAsDesign = read('../../capture/SaveAsDesignDialog.vue')

/** The integer value declared for a --pb-ui-z-* token, or NaN when absent. */
function tokenValue(css: string, name: string): number {
  const m = css.match(new RegExp(`--${name}\\s*:\\s*(\\d+)\\s*;`))
  return m ? Number(m[1]) : Number.NaN
}

/** The z-index expression declared inside the first rule block for a selector. */
function zIndexExpr(css: string, selector: string): string {
  const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const m = css.match(new RegExp(`${escaped}\\s*\\{[^{}]*?z-index:\\s*([^;]+);`))
  return m ? m[1].trim() : ''
}

describe('PB-TEMPLATE-REGISTRY-2-H1 — application-overlay layer tokens', () => {
  it('defines the deliberate overlay band as named tokens', () => {
    for (const name of ['pb-ui-z-live-toolbar', 'pb-ui-z-modal', 'pb-ui-z-modal-nested', 'pb-ui-z-modal-top']) {
      expect(Number.isNaN(tokenValue(tokens, name)), `missing token --${name}`).toBe(false)
    }
  })

  it('orders in-canvas toolbar < modal < nested dialog < top-priority dialog', () => {
    const live = tokenValue(tokens, 'pb-ui-z-live-toolbar')
    const modal = tokenValue(tokens, 'pb-ui-z-modal')
    const nested = tokenValue(tokens, 'pb-ui-z-modal-nested')
    const top = tokenValue(tokens, 'pb-ui-z-modal-top')
    expect(live).toBeLessThan(modal)
    expect(modal).toBeLessThan(nested)
    expect(nested).toBeLessThan(top)
  })

  it('keeps the band scoped to the editor roots, never bare :root', () => {
    expect(/^\s*:root\b/m.test(tokens)).toBe(false)
    expect(tokens).toContain('.pb-editor-root')
    expect(tokens).toContain('.pb-frontend-editor-root')
  })
})

describe('PB-TEMPLATE-REGISTRY-2-H1 — every involved layer owns a semantic token', () => {
  it('the Resource Browser modal + backdrop owns the modal layer', () => {
    expect(zIndexExpr(resourceBrowser, '.pb-rb')).toBe('var(--pb-ui-z-modal)')
  })

  it('the nested Template confirm owns the nested-modal layer', () => {
    expect(zIndexExpr(templateConfirm, '.pb-tc')).toBe('var(--pb-ui-z-modal-nested)')
  })

  it('the in-canvas component toolbar owns the live-toolbar layer', () => {
    expect(zIndexExpr(componentToolbar, '.pb-live-toolbar')).toBe('var(--pb-ui-z-live-toolbar)')
  })

  it('Save as Design stays the top-priority dialog', () => {
    expect(zIndexExpr(saveAsDesign, '.pb-cap')).toBe('var(--pb-ui-z-modal-top)')
  })

  it('no involved layer hardcodes a raw application-overlay integer', () => {
    expect(resourceBrowser).not.toMatch(/\.pb-rb\s*\{[^{}]*?z-index:\s*\d/)
    expect(componentToolbar).not.toMatch(/\.pb-live-toolbar\s*\{[^{}]*?z-index:\s*\d/)
    expect(saveAsDesign).not.toMatch(/\.pb-cap[^{}]*?\{[^{}]*?z-index:\s*\d/)
  })
})

describe('PB-TEMPLATE-REGISTRY-2-H1 — the modal outranks editor chrome', () => {
  it('the modal layer sits strictly above the in-canvas toolbar', () => {
    expect(tokenValue(tokens, 'pb-ui-z-modal')).toBeGreaterThan(tokenValue(tokens, 'pb-ui-z-live-toolbar'))
  })
})
