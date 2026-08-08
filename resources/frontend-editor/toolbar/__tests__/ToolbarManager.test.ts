import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../../../editor/stores/editor'
import { useInlineStore } from '../../inline/InlineManager'
import { useBoxModelStore } from '../../box-model/BoxModelManager'
import { useLiveDragStore } from '../../drag/LiveDragManager'
import { useToolbarStore } from '../ToolbarManager'
import type { ComponentGeometry } from '../types'
import type { LayoutPayload } from '../../../editor/types'

function payload(): LayoutPayload {
  return {
    schema: 'pagebuilder/01',
    sections: [
      { id: 's1', type: 'hero', blocks: [] },
      {
        id: 's2',
        type: 'columns',
        blocks: [
          { id: 'b1', type: 'text', data: {} },
          { id: 'b2', type: 'button', data: {} },
          { id: 'b3', type: 'image', data: {} },
        ],
      },
      { id: 's3', type: 'cta', blocks: [] },
    ],
  } as unknown as LayoutPayload
}

function geometry(componentId = 'b2'): ComponentGeometry {
  return {
    componentId,
    rect: { top: 0, left: 0, width: 100, height: 40 },
    parentId: 's2',
    kind: 'block',
    type: 'button',
  }
}

function selectWithGeometry(id = 'b2') {
  const editor = useEditorStore()
  const toolbar = useToolbarStore()
  editor.$patch({ currentPayload: payload(), selectedComponentId: id })
  toolbar.setGeometry(geometry(id))
  return { editor, toolbar }
}

beforeEach(() => {
  setActivePinia(createPinia())
})

describe('visibility', () => {
  it('is hidden with no selection', () => {
    const toolbar = useToolbarStore()
    toolbar.setGeometry(geometry())
    expect(toolbar.visible).toBe(false)
  })

  it('is visible for a selected component whose geometry is reported', () => {
    const { toolbar } = selectWithGeometry()
    expect(toolbar.visible).toBe(true)
  })

  it('is hidden when the geometry is for a different component', () => {
    const { editor, toolbar } = selectWithGeometry('b2')
    editor.selectComponent('b3')
    expect(toolbar.visible).toBe(false)
  })

  it('is hidden while inline editing', () => {
    const { toolbar } = selectWithGeometry()
    ;(useInlineStore() as unknown as { editor: unknown }).editor = {}
    expect(toolbar.visible).toBe(false)
  })

  it('is hidden while a component drag is active', () => {
    const { editor, toolbar } = selectWithGeometry()
    editor.$patch({ dragActive: true })
    expect(toolbar.visible).toBe(false)
  })

  it('is hidden while a box-model drag is active', () => {
    const { toolbar } = selectWithGeometry()
    ;(useBoxModelStore() as unknown as { session: unknown }).session = {}
    expect(toolbar.visible).toBe(false)
  })
})

describe('identity', () => {
  it('exposes a human label and kind', () => {
    const { toolbar } = selectWithGeometry()
    expect(toolbar.label).toBe('Button')
    expect(toolbar.kindLabel).toBe('Block')
  })
})

describe('menu', () => {
  it('opens, closes and toggles', () => {
    const toolbar = useToolbarStore()
    expect(toolbar.menuOpen).toBe(false)
    toolbar.openMenu()
    expect(toolbar.menuOpen).toBe(true)
    toolbar.closeMenu()
    expect(toolbar.menuOpen).toBe(false)
    toolbar.toggleMenu()
    expect(toolbar.menuOpen).toBe(true)
  })
})

describe('edit / options', () => {
  it('reveals the Options tab for the selection', () => {
    const { toolbar } = selectWithGeometry()
    toolbar.openMenu()
    toolbar.openOptionsForSelected()
    expect(toolbar.activeSidebarTab).toBe('options')
    expect(toolbar.menuOpen).toBe(false)
  })
})

describe('structural actions delegate to the command bus', () => {
  it('duplicate dispatches duplicate-component once', async () => {
    const { editor, toolbar } = selectWithGeometry()
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    await toolbar.duplicateSelected()
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('duplicate-component', { component_id: 'b2' })
  })

  it('copy stores the node without dirtying the payload', () => {
    const { editor, toolbar } = selectWithGeometry()
    toolbar.copySelected()
    expect(editor.clipboard).not.toBeNull()
    expect(editor.dirty).toBe(false)
  })

  it('cut dispatches remove-component', async () => {
    const { editor, toolbar } = selectWithGeometry()
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    await toolbar.cutSelected()
    expect(spy).toHaveBeenCalledWith('remove-component', { component_id: 'b2' })
    expect(editor.clipboard).not.toBeNull()
  })

  it('delete selects the next sibling as a fallback', async () => {
    const { editor, toolbar } = selectWithGeometry('b2')
    vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    await toolbar.deleteSelectedWithFallback()
    expect(editor.selectedComponentId).toBe('b3')
  })

  it('delete falls back to the previous sibling when last', async () => {
    const { editor, toolbar } = selectWithGeometry('b3')
    vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    await toolbar.deleteSelectedWithFallback()
    expect(editor.selectedComponentId).toBe('b2')
  })

  it('move up/down compute the correct target index', async () => {
    const { editor, toolbar } = selectWithGeometry('b1')
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    await toolbar.moveSelectedDown()
    expect(spy).toHaveBeenCalledWith('move-component', {
      component_id: 'b1',
      target_parent_id: 's2',
      index: 1,
    })
  })

  it('does not move when already at the edge', async () => {
    const { editor, toolbar } = selectWithGeometry('b1')
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    await toolbar.moveSelectedUp()
    expect(spy).not.toHaveBeenCalled()
  })

  it('select parent moves selection up the tree', () => {
    const { editor, toolbar } = selectWithGeometry('b2')
    toolbar.selectParent()
    expect(editor.selectedComponentId).toBe('s2')
  })

  it('paste before/after target the correct index', async () => {
    const { editor, toolbar } = selectWithGeometry('b3')
    editor.copyComponent('b1') // clipboard = a block
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    await toolbar.pasteBeforeSelected()
    expect(spy).toHaveBeenCalledWith(
      'insert-component',
      expect.objectContaining({ target_parent_id: 's2', index: 2 }),
    )
  })

  it('paste inside targets the selected container', async () => {
    const { editor, toolbar } = selectWithGeometry('s2')
    editor.copyComponent('b1')
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    await toolbar.pasteInsideSelected()
    expect(spy).toHaveBeenCalledWith(
      'insert-component',
      expect.objectContaining({ target_parent_id: 's2' }),
    )
  })

  // Phase 26.3.8/26.3.10: "Add Element" must open the component picker for the
  // selected container — it must NOT insert a hardcoded default (Text block).
  it('addElement opens the component picker instead of inserting a default', () => {
    const { editor, toolbar } = selectWithGeometry('s2')
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    toolbar.addElementToSelected()
    expect(spy).not.toHaveBeenCalled()
    expect(toolbar.addElementPickerOpen).toBe(true)
  })

  // Phase 26.3.10: picking a component commits through the single guarded
  // insertComponent() path (one command / one undo) targeting the container.
  it('chooseAddElement inserts the picked component into the selected container', async () => {
    const { editor, toolbar } = selectWithGeometry('s2')
    const spy = vi.spyOn(editor, 'insertComponent').mockResolvedValue('new-id')
    toolbar.openAddElementPicker()
    expect(toolbar.addElementPickerOpen).toBe(true)
    await toolbar.chooseAddElement({ type: 'text', label: 'Text' } as never)
    expect(toolbar.addElementPickerOpen).toBe(false)
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith({ parentId: 's2', componentType: 'text' })
  })

  // Phase 26.3.10: the two popups are mutually exclusive.
  it('opening the layout popup closes the add-element picker', () => {
    const { toolbar } = selectWithGeometry('s2')
    toolbar.openAddElementPicker()
    expect(toolbar.addElementPickerOpen).toBe(true)
    toolbar.openLayoutPopup()
    expect(toolbar.addElementPickerOpen).toBe(false)
    expect(toolbar.layoutPopupOpen).toBe(true)
  })

  it('addElement is a no-op with nothing selected', () => {
    const editor = useEditorStore()
    editor.currentPayload = payload()
    editor.selectComponent(null)
    const toolbar = useToolbarStore()
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    toolbar.addElementToSelected()
    expect(spy).not.toHaveBeenCalled()
  })
})

describe('drag handoff', () => {
  it('flushes and starts a cross-iframe drag once (Phase 22.1)', async () => {
    const { toolbar } = selectWithGeometry('b2')
    const liveDrag = useLiveDragStore()
    const spy = vi.spyOn(liveDrag, 'startFromComponent').mockImplementation(() => undefined)
    const event = {} as PointerEvent
    await toolbar.beginDragSelected(event)
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('b2', event)
  })
})

describe('ToolbarManager — sidebar tab lock (Phase 24.2 §13)', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('selectSidebarTab locks the choice; openOptionsForSelected clears it', () => {
    const editor = useEditorStore()
    editor.currentPayload = payload()
    editor.selectComponent('b2')
    const toolbar = useToolbarStore()

    expect(toolbar.sidebarTabLockedByUser).toBe(false)
    toolbar.selectSidebarTab('navigator')
    expect(toolbar.activeSidebarTab).toBe('navigator')
    expect(toolbar.sidebarTabLockedByUser).toBe(true)

    // The explicit Edit/Options action overrides + unlocks.
    toolbar.openOptionsForSelected()
    expect(toolbar.activeSidebarTab).toBe('options')
    expect(toolbar.sidebarTabLockedByUser).toBe(false)
  })

  it('setSidebarTab (automatic) does not lock', () => {
    const toolbar = useToolbarStore()
    toolbar.setSidebarTab('options')
    expect(toolbar.sidebarTabLockedByUser).toBe(false)
  })
})
