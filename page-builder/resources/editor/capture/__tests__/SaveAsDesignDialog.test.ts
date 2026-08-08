// Phase 26.48 — Save as Design dialog + store capture intent. Capturing is an
// external persistence action: it calls the capture endpoint (never a command),
// so BuilderState / undo history are never touched. Duplicate offers Create Copy.
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import SaveAsDesignDialog from '../SaveAsDesignDialog.vue'
import { useEditorStore } from '../../stores/editor'
import type { LayoutPayload } from '../../types'

function payload(): LayoutPayload {
  return {
    schema: 'pagebuilder/01',
    sections: [{ id: 's1', type: 'hero', data: {}, blocks: [{ id: 'b1', type: 'text', data: {} }] }],
  } as unknown as LayoutPayload
}

function store(captureDesign: ReturnType<typeof vi.fn>) {
  const s = useEditorStore()
  s.currentPayload = payload()
  s.owner = null // DesignPreviewPane shows a placeholder — no render fetch in tests.
  s.client = { captureDesign } as never
  s.captureTarget = { node: { type: 'hero', data: {}, blocks: [] }, kind: 'section' }
  return s
}

describe('store capture intent (Phase 26.48)', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('openCapture targets a section / block and ignores unsupported ids', () => {
    const s = useEditorStore()
    s.currentPayload = payload()

    s.openCapture('s1')
    expect(s.captureTarget).toMatchObject({ kind: 'section' })
    expect((s.captureTarget?.node as { type: string }).type).toBe('hero')

    s.openCapture('b1')
    expect(s.captureTarget?.kind).toBe('block')

    s.closeCapture()
    s.openCapture('does-not-exist')
    expect(s.captureTarget).toBeNull()
  })
})

describe('SaveAsDesignDialog (Phase 26.48)', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('saves through the capture endpoint and only closes the dialog (no command)', async () => {
    const captureDesign = vi.fn().mockResolvedValue({ status: 200, data: { status: 'saved', rid: 'my-designs:section:x' } })
    const s = store(captureDesign)
    const execSpy = vi.spyOn(s, 'executeCommand')

    const w = mount(SaveAsDesignDialog, { attachTo: document.body })
    await w.get('[data-testid="cap-name"]').setValue('My Hero')
    await w.get('[data-testid="save"]').trigger('click')
    await Promise.resolve()
    await Promise.resolve()

    expect(captureDesign).toHaveBeenCalledTimes(1)
    const body = captureDesign.mock.calls[0][0]
    expect(body).toMatchObject({ kind: 'section', name: 'My Hero' })
    expect(body.node).toMatchObject({ type: 'hero' })
    // Rule 13/20 — saving closed the dialog and never issued an edit command.
    expect(s.captureTarget).toBeNull()
    expect(execSpy).not.toHaveBeenCalled()
    expect(s.undoStack.length).toBe(0)
  })

  it('shows the duplicate prompt and Create Copy re-submits with mode=copy', async () => {
    const captureDesign = vi
      .fn()
      .mockResolvedValueOnce({ status: 200, data: { status: 'duplicate', existingRid: 'my-designs:section:old' } })
      .mockResolvedValueOnce({ status: 200, data: { status: 'saved', rid: 'my-designs:section:new' } })
    store(captureDesign)

    const w = mount(SaveAsDesignDialog, { attachTo: document.body })
    await w.get('[data-testid="cap-name"]').setValue('Dupe')
    await w.get('[data-testid="save"]').trigger('click')
    await Promise.resolve(); await Promise.resolve()

    expect(w.find('[data-testid="duplicate"]').exists()).toBe(true)
    await w.get('[data-testid="create-copy"]').trigger('click')
    await Promise.resolve(); await Promise.resolve()

    expect(captureDesign).toHaveBeenCalledTimes(2)
    expect(captureDesign.mock.calls[1][0].mode).toBe('copy')
  })

  it('surfaces validation errors and keeps the dialog open', async () => {
    const captureDesign = vi
      .fn()
      .mockResolvedValue({ status: 422, data: { status: 'invalid', issues: [{ code: 'empty', message: 'Nothing to save.' }] } })
    const s = store(captureDesign)

    const w = mount(SaveAsDesignDialog, { attachTo: document.body })
    await w.get('[data-testid="cap-name"]').setValue('X')
    await w.get('[data-testid="save"]').trigger('click')
    await Promise.resolve(); await Promise.resolve()

    expect(w.get('[data-testid="cap-error"]').text()).toContain('Nothing to save.')
    expect(s.captureTarget).not.toBeNull()
  })

  it('Cancel closes without saving', async () => {
    const captureDesign = vi.fn()
    const s = store(captureDesign)

    const w = mount(SaveAsDesignDialog, { attachTo: document.body })
    await w.get('[data-testid="cancel"]').trigger('click')

    expect(captureDesign).not.toHaveBeenCalled()
    expect(s.captureTarget).toBeNull()
  })
})
