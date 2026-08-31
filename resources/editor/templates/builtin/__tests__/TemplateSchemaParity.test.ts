// PB-FREE-LIBRARY-DESIGN-1-F — parity + canonical-schema proof for the three built-in
// Template-Picker templates.
//
// The authoritative Apply source is the TypeScript definition; the PHP
// `BuiltinTemplatePreviewCatalog` is a generation-only projection used by the Preview/
// thumbnail engine. This suite pins the TS definitions to the SAME committed fixture the
// PHP projection is generated from (`tests/Fixtures/PageBuilder/builtin-template-sections.json`),
// so the two authorities can never silently diverge, and asserts the canonical schema:
// heading levels as bounded strings, button links in `url`, section spacing as the box-model
// `spacing.padding.{top,right,bottom,left}`, and NO legacy `padding:{top,bottom}` / `align`.

import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { heroBasic } from '../packs/company/heroBasic'
import { featureGridBasic } from '../packs/company/featureGridBasic'
import { ctaBasic } from '../packs/company/ctaBasic'
import type { TemplateDefinition } from '../../../contracts'

const fixture = JSON.parse(
  readFileSync(resolve(__dirname, '../../../../../tests/Fixtures/PageBuilder/builtin-template-sections.json'), 'utf-8'),
) as Record<string, unknown[]>

const defs: Record<string, TemplateDefinition> = {
  'hero-basic': heroBasic,
  'feature-grid-basic': featureGridBasic,
  'cta-basic': ctaBasic,
}

/** The template's id-free section tree exactly as Apply materializes it. */
function sectionsOf(def: TemplateDefinition): unknown {
  const layout = def.layout as { kind: string; tree?: { sections?: unknown[] } }
  return layout.tree?.sections ?? []
}

/** Recursively collect every heading `level` and button `url`/`href` seen in a tree. */
function scan(node: unknown, acc: { levels: unknown[]; urls: unknown[]; hrefs: unknown[]; padObjects: number; aligns: number }): void {
  if (Array.isArray(node)) {
    node.forEach((n) => scan(n, acc))
    return
  }
  if (!node || typeof node !== 'object') return
  const o = node as Record<string, unknown>
  const type = o.type
  const data = (o.data ?? {}) as Record<string, unknown>
  if (type === 'heading') acc.levels.push(data.level)
  if (type === 'button') {
    if ('url' in data) acc.urls.push(data.url)
    if ('href' in data) acc.hrefs.push(data.href)
  }
  if (type === 'section') {
    // legacy non-canonical fields that must be gone
    if (data.padding && typeof data.padding === 'object') acc.padObjects += 1
    if ('align' in data) acc.aligns += 1
  }
  for (const v of Object.values(o)) scan(v, acc)
}

describe('builtin template schema parity', () => {
  it('every template id has a fixture and a definition', () => {
    expect(Object.keys(fixture).sort()).toEqual(['cta-basic', 'feature-grid-basic', 'hero-basic'])
  })

  for (const id of Object.keys(defs)) {
    describe(id, () => {
      it('TS definition sections match the PHP-generated canonical fixture (no silent drift)', () => {
        expect(sectionsOf(defs[id])).toEqual(fixture[id])
      })

      it('uses canonical heading level strings (h1..h6), never integers', () => {
        const acc = { levels: [], urls: [], hrefs: [], padObjects: 0, aligns: 0 }
        scan(sectionsOf(defs[id]), acc)
        for (const lvl of acc.levels) expect(lvl).toMatch(/^h[1-6]$/)
      })

      it('uses the canonical button `url` field, never `href`', () => {
        const acc = { levels: [], urls: [], hrefs: [], padObjects: 0, aligns: 0 }
        scan(sectionsOf(defs[id]), acc)
        expect(acc.hrefs).toHaveLength(0)
      })

      it('carries no legacy section `padding` object and no section `align`', () => {
        const acc = { levels: [], urls: [], hrefs: [], padObjects: 0, aligns: 0 }
        scan(sectionsOf(defs[id]), acc)
        expect(acc.padObjects).toBe(0)
        expect(acc.aligns).toBe(0)
      })

      it('preserves the stable template id', () => {
        expect(defs[id].id).toBe(id)
      })
    })
  }

  it('hero-basic keeps h1 + a url button + 64px box-model spacing', () => {
    const s = sectionsOf(heroBasic) as Array<Record<string, any>>
    expect(s[0].data.spacing.padding).toEqual({ top: 64, right: 0, bottom: 64, left: 0 })
    const acc = { levels: [], urls: [], hrefs: [], padObjects: 0, aligns: 0 }
    scan(s, acc)
    expect(acc.levels).toEqual(['h1'])
    expect(acc.urls).toEqual(['#'])
  })

  it('feature-grid-basic keeps three h3 headings + 48px spacing', () => {
    const s = sectionsOf(featureGridBasic) as Array<Record<string, any>>
    expect(s[0].data.spacing.padding).toEqual({ top: 48, right: 0, bottom: 48, left: 0 })
    const acc = { levels: [], urls: [], hrefs: [], padObjects: 0, aligns: 0 }
    scan(s, acc)
    expect(acc.levels).toEqual(['h3', 'h3', 'h3'])
  })

  it('cta-basic keeps h2 + a url button + 56px spacing', () => {
    const s = sectionsOf(ctaBasic) as Array<Record<string, any>>
    expect(s[0].data.spacing.padding).toEqual({ top: 56, right: 0, bottom: 56, left: 0 })
    const acc = { levels: [], urls: [], hrefs: [], padObjects: 0, aligns: 0 }
    scan(s, acc)
    expect(acc.levels).toEqual(['h2'])
    expect(acc.urls).toEqual(['#'])
  })
})
