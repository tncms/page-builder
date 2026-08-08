import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../../../editor/stores/editor'
import { useLiveDragStore } from '../LiveDragManager'
import { useBuilderToastStore } from '../../shell/builderToastStore'
import type { LayoutPayload, DefinitionMeta } from '../../../editor/types'
import type { LiveDropResolution } from '../types'

function payload(): LayoutPayload {
  return {
    schema: 'pagebuilder/01',
    sections: [
      { id: 's1', type: 'hero', blocks: [] },
      {
        id: 's2',
        type: 'columns',
        blocks: [{ id: 'colA', type: 'column', data: { blocks: [{ id: 'b1', type: 'button', data: {} }] } }],
      },
    ],
  } as unknown as LayoutPayload
}

let sent: Array<[string, unknown]>

function setup(selected: string | null = 'b1') {
  const editor = useEditorStore()
  editor.$patch({ currentPayload: payload(), selectedComponentId: selected })
  const drag = useLiveDragStore()
  sent = []
  drag.bindSender((type, p) => sent.push([type, p]))
  return { editor, drag }
}

function ev(): PointerEvent {
  return { clientX: 30, clientY: 40 } as PointerEvent
}

function resolution(over: Partial<LiveDropResolution> = {}): LiveDropResolution {
  return {
    valid: true,
    reason: null,
    edge: 'inside',
    targetId: 's1',
    referenceId: 's1',
    parentId: 's1',
    markerRect: { top: 0, left: 0, width: 100, height: 50 },
    ...over,
  }
}

beforeEach(() => setActivePinia(createPinia()))

describe('container ids', () => {
  it('includes every section and container block', () => {
    const { drag } = setup()
    expect(new Set(drag.containerIds())).toEqual(new Set(['s1', 's2', 'colA']))
  })
})

describe('starting a drag', () => {
  it('sends pb:drag-start with the subject + container ids and arms the ghost', () => {
    const { drag } = setup()
    drag.startFromComponent('b1', ev())
    expect(drag.dragging).toBe(true)
    expect(drag.ghost).toEqual({ x: 30, y: 40 })
    const [type, p] = sent[0]
    expect(type).toBe('pb:drag-start')
    expect((p as { subject: { id: string; parentId: string } }).subject).toMatchObject({
      id: 'b1',
      parentId: 'colA',
    })
    expect((p as { containerIds: string[] }).containerIds).toContain('colA')
  })
})

describe('palette drag payload contract (Phase 26.3.11)', () => {
  // A content component dragged from the Elements palette must carry kind:'block'
  // (never 'section') and its component payload, so the resolver treats it as a
  // block and lands it inside a column — not as a top-level section sibling.
  it('sends a palette block subject with kind:block and source:palette', () => {
    const { drag } = setup(null)
    const def = { type: 'image', label: 'Image' } as unknown as DefinitionMeta
    drag.startFromPalette(def, 'block', ev())
    const [type, p] = sent[0]
    expect(type).toBe('pb:drag-start')
    const subject = (p as { subject: { type: string; kind: string; source: string } }).subject
    expect(subject).toMatchObject({ type: 'image', kind: 'block', source: 'palette' })
    // The component payload is retained shell-side for the commit (not shipped
    // to the iframe, which only needs type/kind to resolve the drop).
    expect(drag.subject?.component).toMatchObject({ type: 'image' })
  })
})

describe('target reporting', () => {
  it('stores the resolution and echoes a highlight', () => {
    const { drag } = setup()
    drag.startFromComponent('b1', ev())
    sent.length = 0
    drag.setResolution(resolution())
    expect(drag.valid).toBe(true)
    expect(sent.some(([t]) => t === 'pb:drag-highlight-target')).toBe(true)
  })
})

describe('drop commits exactly one command', () => {
  it('moves an existing component and preserves selection', async () => {
    const { editor, drag } = setup('b1')
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    drag.startFromComponent('b1', ev())
    await drag.drop(resolution({ parentId: 's1', edge: 'inside', referenceId: 's1' }))
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('move-component', {
      component_id: 'b1',
      target_parent_id: 's1',
      index: 0,
    })
    expect(editor.selectedComponentId).toBe('b1')
    expect(drag.dragging).toBe(false)
  })

  it('inserts a palette component', async () => {
    const { editor, drag } = setup(null)
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    const def = { type: 'text', label: 'Text' } as unknown as DefinitionMeta
    drag.startFromPalette(def, 'block', ev())
    await drag.drop(resolution({ parentId: 'colA', edge: 'inside', referenceId: 'colA' }))
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith(
      'insert-component',
      expect.objectContaining({ target_parent_id: 'colA', index: 1 }),
    )
  })

  it('commits nothing for an invalid drop', async () => {
    const { editor, drag } = setup('b1')
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    drag.startFromComponent('b1', ev())
    await drag.drop(resolution({ valid: false }))
    expect(spy).not.toHaveBeenCalled()
    expect(drag.dragging).toBe(false)
  })
})

describe('cancel', () => {
  it('resets and tells the iframe to cancel', () => {
    const { drag } = setup('b1')
    drag.startFromComponent('b1', ev())
    drag.cancel()
    expect(drag.dragging).toBe(false)
    expect(sent.some(([t]) => t === 'pb:drag-cancel')).toBe(true)
  })
})

// Phase 26.3.18 — informational (non-blocking) toast when a Section snaps to page
// level while the pointer was aimed inside content. The drop OUTCOME is unchanged
// (Sections still snap to the root); we only explain it.
describe('section snap-to-page-level guidance toast', () => {
  const sectionDef = { type: 'image-section', label: 'Image Section' } as unknown as DefinitionMeta

  it('shows the toast when a section drop snapped from inside a column', async () => {
    const { editor, drag } = setup(null)
    vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    drag.startFromPalette(sectionDef, 'section', ev())
    // Aimed inside colA (targetId) but snapped next to root section s1 (referenceId).
    await drag.drop(resolution({ parentId: null, referenceId: 's1', targetId: 'colA', edge: 'before' }))
    expect(useBuilderToastStore().toasts).toHaveLength(1)
    expect(useBuilderToastStore().toasts[0].message).toMatch(/page level/i)
  })

  it('does NOT show the toast for a clean root-gap section drop', async () => {
    const { editor, drag } = setup(null)
    vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    drag.startFromPalette(sectionDef, 'section', ev())
    // targetId === referenceId → dropped at a page-level gap, not inside content.
    await drag.drop(resolution({ parentId: null, referenceId: 's1', targetId: 's1', edge: 'before' }))
    expect(useBuilderToastStore().toasts).toHaveLength(0)
  })
})
