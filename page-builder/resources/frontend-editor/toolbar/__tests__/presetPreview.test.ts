// Phase 26.1.1 — presetPreview: preview-segment resolution.
import { describe, it, expect } from 'vitest'
import { previewSegments, previewType } from '../presetPreview'

describe('previewSegments', () => {
  it('uses the preview descriptor segments when present', () => {
    expect(previewSegments({ preview: { type: 'bars', segments: [30, 70] } })).toEqual([30, 70])
  })

  it('derives segments from widths when no preview descriptor', () => {
    expect(previewSegments({ widths: ['50', '50'] })).toEqual([50, 50])
  })

  it('prefers preview over widths', () => {
    expect(
      previewSegments({ widths: ['50', '50'], preview: { type: 'bars', segments: [25, 75] } }),
    ).toEqual([25, 75])
  })

  it('drops invalid segment values', () => {
    expect(previewSegments({ preview: { type: 'bars', segments: [0, 60, -5] } as never })).toEqual([60])
  })

  it('falls back to a single bar when nothing resolves', () => {
    expect(previewSegments({})).toEqual([1])
    expect(previewSegments({ widths: [], preview: { type: 'bars', segments: [] } })).toEqual([1])
  })
})

describe('previewType', () => {
  it('returns the declared type, defaulting to bars', () => {
    expect(previewType({ preview: { type: 'svg', segments: [1] } })).toBe('svg')
    expect(previewType({})).toBe('bars')
  })
})
