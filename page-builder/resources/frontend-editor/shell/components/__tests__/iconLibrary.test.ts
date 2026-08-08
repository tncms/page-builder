import { describe, it, expect } from 'vitest'
import { resolveIconPath, resolveIconSvg } from '../iconLibrary'

describe('iconLibrary (Phase 23)', () => {
  it('resolves a known heroicon name to SVG path data', () => {
    const d = resolveIconPath('heroicon-o-photo')
    expect(d).toMatch(/^M/) // an SVG path
    expect(d).not.toContain('heroicon')
  })

  it('never returns the raw heroicon string for an unknown icon', () => {
    const d = resolveIconPath('heroicon-o-totally-unknown-icon', 'media')
    expect(d).toMatch(/^M/)
    expect(d).not.toContain('heroicon')
    // Falls back to the category icon (media → photo).
    expect(d).toBe(resolveIconPath('heroicon-o-photo'))
  })

  it('falls back to a generic glyph with no icon or category', () => {
    const d = resolveIconPath(null, null)
    expect(d).toMatch(/^M/)
  })

  it('builds a full inline <svg> string for v-html contexts', () => {
    const svg = resolveIconSvg('heroicon-o-megaphone', 'conversion')
    expect(svg).toContain('<svg')
    expect(svg).toContain('<path')
    expect(svg).not.toContain('heroicon')
  })
})
