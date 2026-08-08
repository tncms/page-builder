import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../stores/editor'
import { createComponentPayload } from '../utils/palette'
import type { DefinitionMeta, LayoutPreset } from '../types'

const TWO_COL: LayoutPreset = {
  id: 'layout.2col',
  label: '2 Columns',
  icon: 'heroicon-o-view-columns',
  columns: 2,
  sections: [
    {
      type: 'section',
      data: { width: 'normal', padding: 'md' },
      blocks: [
        {
          type: 'container',
          data: {
            layout: 'flex',
            width: 'normal',
            gap: 'md',
            blocks: [
              { type: 'column', data: { width: '50', blocks: [] } },
              { type: 'column', data: { width: '50', blocks: [] } },
            ],
          },
        },
      ],
    },
  ],
}

beforeEach(() => {
  setActivePinia(createPinia())
})

describe('layout presets (Phase 25.3)', () => {
  it('inserts a preset as ONE insert-pattern command (one undo entry)', async () => {
    const store = useEditorStore()
    const execute = vi.spyOn(store, 'executeCommand').mockResolvedValue(true)

    await store.addPresetFromPalette(TWO_COL)

    expect(execute).toHaveBeenCalledTimes(1)
    expect(execute).toHaveBeenCalledWith('insert-pattern', { sections: TWO_COL.sections })
  })

  it('exposes presets from editor metadata for the palette', () => {
    const store = useEditorStore()
    ;(store as unknown as { metadata: unknown }).metadata = {
      version: '1', sections: [], blocks: [], tokens: {}, breakpoints: {}, presets: [TWO_COL],
    }

    expect(store.metadata?.presets).toHaveLength(1)
    expect(store.metadata?.presets?.[0].columns).toBe(2)
  })

  it('builds a Container payload ready to hold Columns (data.blocks seam)', () => {
    const container: DefinitionMeta = {
      type: 'container',
      defaults: { layout: 'flex', width: 'normal', blocks: [] },
    } as unknown as DefinitionMeta

    const payload = createComponentPayload(container, 'block')

    expect(payload.type).toBe('container')
    expect(Array.isArray((payload.data as Record<string, unknown>).blocks)).toBe(true)
  })
})
