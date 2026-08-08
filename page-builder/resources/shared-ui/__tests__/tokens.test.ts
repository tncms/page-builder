import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const read = (rel: string): string => readFileSync(resolve(here, rel), 'utf8')
const stripComments = (css: string): string => css.replace(/\/\*[\s\S]*?\*\//g, '')

const tokens = read('../tokens.css')
const primitives = read('../primitives.css')
const index = read('../index.css')
const editorMain = read('../../editor/main.ts')
const shellMain = read('../../frontend-editor/shell/main.ts')
const pkg = read('../../../package.json')

describe('PB-UI design tokens', () => {
  it('exposes every token category', () => {
    for (const token of [
      '--pb-ui-color-surface',
      '--pb-ui-color-border',
      '--pb-ui-color-text',
      '--pb-ui-color-primary',
      '--pb-ui-color-focus-ring',
      '--pb-ui-space-8',
      '--pb-ui-radius-md',
      '--pb-ui-shadow-md',
      '--pb-ui-font-family',
      '--pb-ui-font-size-md',
      '--pb-ui-duration-normal',
      '--pb-ui-easing-standard',
      '--pb-ui-z-dialog',
      '--pb-ui-topbar-height',
      '--pb-ui-control-height',
      '--pb-ui-bp-tablet',
    ]) {
      expect(tokens, `missing token ${token}`).toContain(token)
    }
  })

  it('declares every custom property under the --pb-ui- namespace', () => {
    const declared = tokens.match(/^\s*--[a-z0-9-]+\s*:/gim) ?? []
    expect(declared.length).toBeGreaterThan(20)
    const foreign = declared.filter((d) => !/^\s*--pb-ui-/.test(d))
    expect(foreign, `non-namespaced tokens: ${foreign.join(', ')}`).toEqual([])
  })

  it('scopes tokens to the editor roots, never bare :root', () => {
    expect(tokens).toContain('.pb-editor-root')
    expect(tokens).toContain('.pb-frontend-editor-root')
    expect(/^\s*:root\b/m.test(tokens)).toBe(false)
  })
})

describe('PB-UI single source, consumed by both bundles', () => {
  it('index.css imports the token and primitive layers', () => {
    expect(index).toContain('./tokens.css')
    expect(index).toContain('./primitives.css')
  })

  it('both editor bundles import the one shared source', () => {
    expect(editorMain).toContain('shared-ui/index.css')
    expect(shellMain).toContain('shared-ui/index.css')
  })
})

describe('PB-UI forbidden patterns', () => {
  it('introduces no Bootstrap/Tailwind dependency', () => {
    expect(/bootstrap|tailwind/i.test(pkg)).toBe(false)
  })

  it('primitives use no generic element selectors (no global leakage)', () => {
    const generic = /^\s*(button|input|select|textarea|a|h[1-6]|ul|ol|li|table|p)\s*[,{]/im
    expect(generic.test(stripComments(primitives))).toBe(false)
  })

  it('primitives use no !important', () => {
    expect(stripComments(primitives)).not.toContain('!important')
  })

  it('primitives hardcode no colors — tokens only', () => {
    const code = stripComments(primitives)
    const hex = code.match(/#[0-9a-fA-F]{3,8}\b/g) ?? []
    const rgb = code.match(/\brgba?\(/g) ?? []
    expect(hex, `hardcoded hex: ${hex.join(', ')}`).toEqual([])
    expect(rgb, `hardcoded rgb: ${rgb.join(', ')}`).toEqual([])
  })
})
