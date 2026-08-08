// Phase 26.1.1 — layoutHoverPreview: send patches on hover, restore on leave.
// The preview must NEVER commit, mark dirty, or create an undo entry.
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../../../editor/stores/editor'
import { useLayoutHoverPreview } from '../layoutHoverPreview'
import type { LayoutPayload } from '../../../editor/types'

function payload(): LayoutPayload {
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
              blocks: [
                { id: 'col1', type: 'column', data: { width: '50' } },
                { id: 'col2', type: 'column', data: { width: '50' } },
              ],
            },
          },
        ],
      },
    ],
  } as unknown as LayoutPayload
}

beforeEach(() => setActivePinia(createPinia()))

describe('preview', () => {
  it('sends a flex-basis/max-width/width patch for each existing column', () => {
    const editor = useEditorStore()
    editor.$patch({ currentPayload: payload() })
    const hover = useLayoutHoverPreview()
    const sender = vi.fn()
    hover.bindSender(sender)

    hover.preview('c1', ['30', '70'], 'sidebar')

    expect(sender).toHaveBeenCalledTimes(2)
    expect(sender).toHaveBeenNthCalledWith(1, 'pb:patch-style', {
      componentId: 'col1',
      selector: null,
      style: { flexBasis: '30%', maxWidth: '30%', width: '30%' },
    })
    expect(sender).toHaveBeenNthCalledWith(2, 'pb:patch-style', {
      componentId: 'col2',
      selector: null,
      style: { flexBasis: '70%', maxWidth: '70%', width: '70%' },
    })
    expect(hover.previewing).toBe(true)
  })

  it('only re-proportions the overlapping columns when the preset has more widths', () => {
    const editor = useEditorStore()
    editor.$patch({ currentPayload: payload() })
    const hover = useLayoutHoverPreview()
    const sender = vi.fn()
    hover.bindSender(sender)

    // 3-column preset over a 2-column container → only 2 patches.
    hover.preview('c1', ['33', '33', '33'], 'thirds')
    expect(sender).toHaveBeenCalledTimes(2)
  })

  it('does not re-send when the same preset is hovered again', () => {
    const editor = useEditorStore()
    editor.$patch({ currentPayload: payload() })
    const hover = useLayoutHoverPreview()
    const sender = vi.fn()
    hover.bindSender(sender)

    hover.preview('c1', ['30', '70'], 'sidebar')
    sender.mockClear()
    hover.preview('c1', ['30', '70'], 'sidebar')
    expect(sender).not.toHaveBeenCalled()
  })
})

describe('restore', () => {
  it('re-sends each column its captured original width', () => {
    const editor = useEditorStore()
    editor.$patch({ currentPayload: payload() })
    const hover = useLayoutHoverPreview()
    const sender = vi.fn()
    hover.bindSender(sender)

    hover.preview('c1', ['30', '70'], 'sidebar')
    sender.mockClear()
    hover.restore()

    expect(sender).toHaveBeenCalledTimes(2)
    expect(sender).toHaveBeenNthCalledWith(1, 'pb:patch-style', {
      componentId: 'col1',
      selector: null,
      style: { flexBasis: '50%', maxWidth: '50%', width: '50%' },
    })
    expect(hover.previewing).toBe(false)
  })

  it('does not touch the payload, dirty flag, or undo (preview only)', () => {
    const editor = useEditorStore()
    editor.$patch({ currentPayload: payload() })
    const before = JSON.stringify(editor.currentPayload)
    const hover = useLayoutHoverPreview()
    hover.bindSender(vi.fn())

    hover.preview('c1', ['30', '70'], 'sidebar')
    hover.restore()

    expect(JSON.stringify(editor.currentPayload)).toBe(before)
    expect(editor.dirty).toBe(false)
    expect(editor.canUndo).toBe(false)
  })

  it('restore is a no-op when nothing is being previewed', () => {
    const hover = useLayoutHoverPreview()
    const sender = vi.fn()
    hover.bindSender(sender)
    hover.restore()
    expect(sender).not.toHaveBeenCalled()
  })

  it("clears the patch (not 'auto%') when restoring a non-numeric original width", () => {
    const editor = useEditorStore()
    const p = payload()
    // A default 'auto' column: restore must clear the inline patch, not write
    // an invalid 'auto%' the CSSOM drops (which would leave a stale preview).
    ;(p.sections[0].blocks[0] as { data: { blocks: { data: { width: string } }[] } }).data.blocks[0].data.width = 'auto'
    editor.$patch({ currentPayload: p })
    const hover = useLayoutHoverPreview()
    const sender = vi.fn()
    hover.bindSender(sender)

    hover.preview('c1', ['30', '70'], 'sidebar')
    sender.mockClear()
    hover.restore()

    expect(sender).toHaveBeenNthCalledWith(1, 'pb:patch-style', {
      componentId: 'col1',
      selector: null,
      style: { flexBasis: '', maxWidth: '', width: '' },
    })
  })
})
