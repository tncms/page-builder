import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useInlineStore } from '../InlineManager'
import { useEditorStore } from '../../../editor/stores/editor'
import type { InlineTarget } from '../SelectionManager'

function target(part: Partial<InlineTarget> = {}): InlineTarget {
  return {
    componentId: 'cta-1',
    type: 'cta',
    field: 'title',
    selector: '.pb-cta__title',
    multiline: false,
    translatable: true,
    value: 'Old',
    rect: { top: 0, left: 0, width: 100, height: 20 },
    typography: {},
    ...part,
  }
}

interface Sent {
  t: string
  p: Record<string, unknown>
}

function setup() {
  setActivePinia(createPinia())
  const editor = useEditorStore()
  editor.currentPayload = {
    schema: 'pagebuilder/01',
    sections: [{ type: 'cta', id: 'cta-1', data: { title: { vi: 'Old' } }, blocks: [] }],
  }
  const update = vi.fn().mockResolvedValue(true)
  editor.updateComponentDataById = update as never

  const inline = useInlineStore()
  const sent: Sent[] = []
  inline.bindSender((t, p) => sent.push({ t, p }))
  return { inline, update, sent }
}

describe('InlineManager', () => {
  beforeEach(() => setup())

  it('opens a session and live-patches on typing without committing', () => {
    const { inline, update, sent } = setup()
    inline.open(target())
    expect(inline.active).toBe(true)

    inline.setValue('New')
    expect(inline.value).toBe('New')
    expect(sent).toContainEqual({ t: 'pb:patch-text', p: { componentId: 'cta-1', selector: '.pb-cta__title', text: 'New' } })
    // Typing alone never commits.
    expect(update).not.toHaveBeenCalled()
  })

  it('commits once through update-component-data and ends the session', async () => {
    const { inline, update, sent } = setup()
    inline.open(target())
    inline.setValue('New')

    await inline.commit()

    expect(update).toHaveBeenCalledTimes(1)
    expect(update).toHaveBeenCalledWith('cta-1', { title: { vi: 'New' } })
    expect(sent.some((m) => m.t === 'pb:inline-end')).toBe(true)
    expect(inline.active).toBe(false)
  })

  it('does not run a command when nothing changed', async () => {
    const { inline, update, sent } = setup()
    inline.open(target())
    await inline.commit()

    expect(update).not.toHaveBeenCalled()
    expect(sent.some((m) => m.t === 'pb:inline-end')).toBe(true)
    expect(inline.active).toBe(false)
  })

  it('cancel restores the original text and runs no command', () => {
    const { inline, update, sent } = setup()
    inline.open(target())
    inline.setValue('New')
    inline.cancel()

    expect(update).not.toHaveBeenCalled()
    expect(sent).toContainEqual({ t: 'pb:patch-text', p: { componentId: 'cta-1', selector: '.pb-cta__title', text: 'Old' } })
    expect(sent.some((m) => m.t === 'pb:inline-end')).toBe(true)
    expect(inline.active).toBe(false)
  })

  it('flush commits an open dirty session', async () => {
    const { inline, update } = setup()
    inline.open(target())
    inline.setValue('New')
    await inline.flush()
    expect(update).toHaveBeenCalledTimes(1)
    expect(inline.active).toBe(false)
  })

  it('flush is a no-op with no open session', async () => {
    const { inline, update } = setup()
    await inline.flush()
    expect(update).not.toHaveBeenCalled()
  })
})
