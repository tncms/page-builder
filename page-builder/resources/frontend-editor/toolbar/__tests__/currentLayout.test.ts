// Phase 26.1.1 — currentLayout: detect which preset a container matches.
import { describe, it, expect } from 'vitest'
import {
  containerColumnWidths,
  widthsMatch,
  matchPresetId,
  currentLayoutPresetId,
} from '../currentLayout'
import type { ColumnLayoutPreset, LayoutPayload } from '../../../editor/types'

function preset(id: string, widths: string[]): ColumnLayoutPreset {
  return { id, label: id, widths }
}

const PRESETS: ColumnLayoutPreset[] = [
  preset('halves', ['50', '50']),
  preset('thirds', ['33.3333', '33.3333', '33.3333']),
  preset('sidebar', ['30', '70']),
]

function payload(widths: string[]): LayoutPayload {
  return {
    schema: 'pagebuilder/01',
    sections: [
      {
        id: 's1',
        type: 'section',
        blocks: [
          {
            id: 'c1',
            type: 'container',
            data: {
              blocks: widths.map((w, i) => ({ id: `col${i}`, type: 'column', data: { width: w } })),
            },
          },
        ],
      },
    ],
  } as unknown as LayoutPayload
}

describe('containerColumnWidths', () => {
  it('reads numeric widths from each column child', () => {
    expect(containerColumnWidths(payload(['50', '50']), 'c1')).toEqual([50, 50])
  })

  it('returns [] for an unknown container', () => {
    expect(containerColumnWidths(payload(['50', '50']), 'nope')).toEqual([])
  })
})

describe('widthsMatch (tolerance)', () => {
  it('treats 33 / 33.33 / 33.3333 as equal thirds', () => {
    expect(widthsMatch([33, 33, 33], [33.3333, 33.3333, 33.3333])).toBe(true)
    expect(widthsMatch([33.33, 33.33, 33.34], [33.3333, 33.3333, 33.3333])).toBe(true)
  })

  it('normalises to shares so ratios match regardless of scale', () => {
    expect(widthsMatch([1, 1], [50, 50])).toBe(true)
  })

  it('rejects different proportions', () => {
    expect(widthsMatch([30, 70], [50, 50])).toBe(false)
  })

  it('rejects a different column count', () => {
    expect(widthsMatch([50, 50], [33, 33, 33])).toBe(false)
  })
})

describe('matchPresetId', () => {
  it('matches a preset exactly', () => {
    expect(matchPresetId([50, 50], PRESETS)).toBe('halves')
  })

  it('matches thirds within tolerance', () => {
    expect(matchPresetId([33, 33, 33], PRESETS)).toBe('thirds')
  })

  it('returns null for a custom layout', () => {
    expect(matchPresetId([40, 60], PRESETS)).toBeNull()
  })
})

describe('currentLayoutPresetId', () => {
  it('resolves the matching preset straight from the payload', () => {
    expect(currentLayoutPresetId(payload(['30', '70']), 'c1', PRESETS)).toBe('sidebar')
  })

  it('returns null when nothing matches (custom)', () => {
    expect(currentLayoutPresetId(payload(['10', '90']), 'c1', PRESETS)).toBeNull()
  })
})
