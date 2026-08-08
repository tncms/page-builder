// Phase 22.1 — cross-iframe drag integration. Wires the REAL iframe drag
// controller (over a stubbed marker DOM) to the REAL shell drag store through a
// two-way message relay, then drives it with real pointer events — exercising
// the full start → move → resolve → drop lifecycle end to end.

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../../../editor/stores/editor'
import { useLiveDragStore } from '../LiveDragManager'
import { IframeDragController } from '../../iframe/drag/IframeDragController'
import { AutoScroll } from '../../../editor/drag/AutoScroll'
import { standardCanvas } from '../../iframe/drag/__tests__/dom'
import { BRIDGE_SOURCE } from '../../bridge/messages'
import type { LayoutPayload } from '../../../editor/types'
import type { LiveDropResolution } from '../types'

function payload(): LayoutPayload {
  return {
    schema: 'pagebuilder/01',
    sections: [
      { id: 's1', type: 'hero', blocks: [] },
      {
        id: 's2',
        type: 'columns',
        blocks: [
          { id: 'colA', type: 'column', data: { blocks: [{ id: 'b1', type: 'button', data: {} }] } },
          { id: 'colB', type: 'column', data: { blocks: [] } },
        ],
      },
      { id: 's3', type: 'cta', blocks: [] },
    ],
  } as unknown as LayoutPayload
}

let controller: IframeDragController

function harness(selected: string | null = null) {
  const editor = useEditorStore()
  editor.$patch({ currentPayload: payload(), selectedComponentId: selected })
  const drag = useLiveDragStore()
  const canvas = standardCanvas()

  // iframe -> shell relay (no iframe offset in the test).
  const toShell = (type: string, p?: unknown): void => {
    switch (type) {
      case 'pb:drag-pointer': {
        const c = p as { x: number; y: number }
        drag.updatePointer(c.x, c.y)
        break
      }
      case 'pb:drag-target':
        drag.setResolution(p as LiveDropResolution)
        break
      case 'pb:drag-leave':
        drag.clearResolution()
        break
      case 'pb:drag-drop':
        void drag.drop(p as LiveDropResolution)
        break
      case 'pb:drag-cancelled':
        drag.cancel()
        break
      default:
        break
    }
  }
  controller = new IframeDragController(canvas, toShell)

  // shell -> iframe relay.
  drag.bindSender((type, p) => controller.onShellMessage({ source: BRIDGE_SOURCE, type: type as never, payload: p }))

  return { editor, drag, canvas }
}

function move(x: number, y: number): void {
  document.dispatchEvent(new MouseEvent('pointermove', { clientX: x, clientY: y }))
}
function up(x: number, y: number): void {
  document.dispatchEvent(new MouseEvent('pointerup', { clientX: x, clientY: y }))
}
function pointerdown(): PointerEvent {
  return { clientX: 5, clientY: 5 } as PointerEvent
}

beforeEach(() => setActivePinia(createPinia()))
afterEach(() => {
  // Stop any live controller so its document listeners don't leak between tests.
  controller?.onShellMessage({ source: BRIDGE_SOURCE, type: 'pb:drag-cancel' as never })
  document.body.innerHTML = ''
})

describe('session + target reporting', () => {
  it('a toolbar drag starts the iframe session and reports the target', () => {
    const { drag } = harness('s1')
    drag.startFromComponent('s1', pointerdown())
    expect(drag.dragging).toBe(true)
    move(250, 380) // over s3, lower half
    expect(drag.valid).toBe(true)
    expect(drag.resolution?.referenceId).toBe('s3')
    expect(drag.resolution?.edge).toBe('after')
  })

  it('does not mutate the payload during pointermove', () => {
    const { editor, drag } = harness('b1')
    const before = JSON.stringify(editor.currentPayload)
    drag.startFromComponent('b1', pointerdown())
    move(300, 200)
    move(100, 130)
    move(250, 380)
    expect(JSON.stringify(editor.currentPayload)).toBe(before)
  })
})

describe('drop commits exactly one command', () => {
  it('reorders a root section on drop', () => {
    const { editor, drag } = harness('s1')
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    drag.startFromComponent('s1', pointerdown())
    move(250, 380)
    up(250, 380)
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('move-component', { component_id: 's1', target_parent_id: null, index: 2 })
  })

  it('moves a block into another (empty) container', () => {
    const { editor, drag } = harness('b1')
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    drag.startFromComponent('b1', pointerdown())
    move(300, 200) // colB centre → inside
    up(300, 200)
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('move-component', { component_id: 'b1', target_parent_id: 'colB', index: 0 })
  })

  it('keeps the moved component selected', async () => {
    const { editor, drag } = harness('b1')
    vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    drag.startFromComponent('b1', pointerdown())
    move(300, 200)
    up(300, 200)
    await Promise.resolve()
    await Promise.resolve()
    expect(editor.selectedComponentId).toBe('b1')
  })
})

describe('invalid drops', () => {
  it('rejects dropping a container into its own descendant and commits nothing', () => {
    const { editor, drag } = harness('colA')
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    drag.startFromComponent('colA', pointerdown())
    move(100, 130) // over b1, a descendant of colA
    expect(drag.valid).toBe(false)
    up(100, 130)
    expect(spy).not.toHaveBeenCalled()
  })
})

describe('cancel', () => {
  it('Escape cancels the session without committing', () => {
    const { editor, drag } = harness('b1')
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    drag.startFromComponent('b1', pointerdown())
    move(300, 200)
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    expect(drag.dragging).toBe(false)
    up(300, 200)
    expect(spy).not.toHaveBeenCalled()
  })
})

describe('palette drag', () => {
  it('inserts a section at the root on an empty-area drop', () => {
    const { editor, drag } = harness(null)
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    drag.startFromPalette({ type: 'hero', label: 'Hero' } as never, 'section', pointerdown())
    move(250, 500) // empty area below s3, still within the canvas
    up(250, 500)
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith(
      'insert-component',
      expect.objectContaining({ target_parent_id: null, index: 3 }),
    )
  })

  it('inserts a block into a container', () => {
    const { editor, drag } = harness(null)
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    drag.startFromPalette({ type: 'text', label: 'Text' } as never, 'block', pointerdown())
    move(300, 200) // colB centre
    up(300, 200)
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith(
      'insert-component',
      expect.objectContaining({ target_parent_id: 'colB', index: 0 }),
    )
  })

  // Phase 26.3.9: prove the FULL live round-trip (not just the resolver) lands an
  // element INSIDE the column when dropped on its edge or into a populated column
  // — the nested-container redirect must survive the shell↔iframe commit path.
  it('drops a Text on the empty column TOP edge INSIDE the column', () => {
    const { editor, drag } = harness(null)
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    drag.startFromPalette({ type: 'text', label: 'Text' } as never, 'block', pointerdown())
    move(300, 120) // colB top third → "before", redirected inside
    up(300, 120)
    expect(spy).toHaveBeenCalledWith(
      'insert-component',
      expect.objectContaining({ target_parent_id: 'colB' }),
    )
  })

  it('drops an Image on the empty column BOTTOM edge INSIDE the column', () => {
    const { editor, drag } = harness(null)
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    drag.startFromPalette({ type: 'image', label: 'Image' } as never, 'block', pointerdown())
    move(300, 280) // colB bottom third → "after", redirected inside
    up(300, 280)
    expect(spy).toHaveBeenCalledWith(
      'insert-component',
      expect.objectContaining({ target_parent_id: 'colB' }),
    )
  })

  it('drops a Button into the empty area of a POPULATED column INSIDE that column', () => {
    const { editor, drag } = harness(null)
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    drag.startFromPalette({ type: 'button', label: 'Button' } as never, 'block', pointerdown())
    move(100, 280) // colA, below child b1 but inside the column
    up(100, 280)
    expect(spy).toHaveBeenCalledWith(
      'insert-component',
      expect.objectContaining({ target_parent_id: 'colA' }),
    )
  })

  it('drops a Heading over a column child and lands it beside the child in the same column', () => {
    const { editor, drag } = harness(null)
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    drag.startFromPalette({ type: 'heading', label: 'Heading' } as never, 'block', pointerdown())
    move(100, 115) // over child b1 (leaf) → parent is colA
    up(100, 115)
    expect(spy).toHaveBeenCalledWith(
      'insert-component',
      expect.objectContaining({ target_parent_id: 'colA' }),
    )
  })

  // Phase 26.3.14 — every content BLOCK lands inside the column, regardless of
  // type. Quote works today; these lock the ones the differential flagged.
  it.each([['quote'], ['icon'], ['button'], ['image'], ['text']])(
    'drops a %s block inside the empty column',
    (type) => {
      const { editor, drag } = harness(null)
      const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
      drag.startFromPalette({ type, label: type } as never, 'block', pointerdown())
      move(300, 200) // colB centre
      up(300, 200)
      expect(spy).toHaveBeenCalledWith(
        'insert-component',
        expect.objectContaining({ target_parent_id: 'colB' }),
      )
    },
  )
})

describe('auto-scroll velocity', () => {
  it('ramps toward the edge the pointer is nearest', () => {
    const scroll = new AutoScroll({ edge: 60, maxSpeed: 18 })
    const rect = { top: 0, bottom: 600, left: 0, right: 500 }
    expect(scroll.velocityFor(rect, 250, 5).vy).toBeLessThan(0) // near top → scroll up
    expect(scroll.velocityFor(rect, 250, 595).vy).toBeGreaterThan(0) // near bottom → down
    expect(scroll.velocityFor(rect, 250, 300).vy).toBe(0) // middle → none
  })
})
