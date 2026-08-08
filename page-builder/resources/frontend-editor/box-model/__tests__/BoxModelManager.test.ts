import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useBoxModelStore, isSupportedType } from '../BoxModelManager'
import { useEditorStore } from '../../../editor/stores/editor'
import type { BoxMetrics } from '../types'

function metrics(): BoxMetrics {
  return {
    componentId: 'hero-1',
    margin: { top: 0, right: 0, bottom: 10, left: 0 },
    padding: { top: 20, right: 0, bottom: 0, left: 0 },
    border: { top: 0, right: 0, bottom: 0, left: 0 },
    rect: { top: 0, left: 0, width: 100, height: 50 },
  }
}

function setup() {
  setActivePinia(createPinia())
  const editor = useEditorStore()
  editor.currentPayload = {
    schema: 'pagebuilder/01',
    sections: [{ type: 'hero', id: 'hero-1', data: { title: 'Hi' }, blocks: [] }],
  }
  editor.selectedComponentId = 'hero-1'
  const update = vi.fn().mockResolvedValue(true)
  editor.updateComponentDataById = update as never

  const box = useBoxModelStore()
  const sent: Array<{ t: string; p: Record<string, unknown> }> = []
  box.bindSender((t, p) => sent.push({ t, p }))
  box.setMetrics(metrics())
  return { box, editor, update, sent }
}

describe('isSupportedType', () => {
  it('gates to the built-in supported set', () => {
    expect(isSupportedType('hero')).toBe(true)
    expect(isSupportedType('cta')).toBe(true)
    expect(isSupportedType('testimonial')).toBe(false)
    expect(isSupportedType(null)).toBe(false)
  })
})

describe('BoxModelManager', () => {
  beforeEach(() => setup())

  it('is visible for a supported, selected, measured component', () => {
    const { box } = setup()
    expect(box.visible).toBe(true)
  })

  it('live-patches while dragging without committing', () => {
    const { box, update, sent } = setup()
    box.startDrag('padding', 'top')
    expect(box.dragging).toBe(true)

    box.updateDrag(0, -10) // drag top handle up 10px -> padding.top 20 + 10 = 30
    expect(box.dragValue).toBe(30)
    expect(sent).toContainEqual({
      t: 'pb:patch-style',
      p: { componentId: 'hero-1', selector: null, style: { paddingTop: '30px' } },
    })
    expect(update).not.toHaveBeenCalled()
  })

  it('commits the merged spacing on release', async () => {
    const { box, update } = setup()
    box.startDrag('padding', 'top')
    box.updateDrag(0, -10)
    await box.endDrag()

    expect(update).toHaveBeenCalledTimes(1)
    expect(update).toHaveBeenCalledWith('hero-1', {
      title: 'Hi',
      spacing: { padding: { top: 30 }, margin: {} },
    })
    expect(box.dragging).toBe(false)
  })

  it('cancel restores the original value and runs no command', () => {
    const { box, update, sent } = setup()
    box.startDrag('margin', 'bottom')
    box.updateDrag(0, 40)
    box.cancel()

    expect(update).not.toHaveBeenCalled()
    // Last patch restores the pre-drag value (10px).
    expect(sent.at(-1)).toEqual({
      t: 'pb:patch-style',
      p: { componentId: 'hero-1', selector: null, style: { marginBottom: '10px' } },
    })
    expect(box.dragging).toBe(false)
  })
})
