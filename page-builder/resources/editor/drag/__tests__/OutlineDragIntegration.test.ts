import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../../stores/editor'
import { outlineDragController } from '../index'
import type { OutlineZone } from '../outline'
import type { LayoutPayload, EditorMetadata } from '../../types'

const metadata = {
  version: '1',
  sections: [
    { type: 'hero', label: 'Hero', defaults: { heading: 'Hi' } },
    { type: 'cta', label: 'CTA', defaults: {} },
  ],
  blocks: [{ type: 'button', label: 'Button', defaults: { text: 'Go' } }],
  tokens: {},
} as unknown as EditorMetadata

// ---- A faithful in-memory command runner (insert + move) -----------------
let counter = 0
function childList(node: any): any[] {
  if (Array.isArray(node.blocks)) return node.blocks
  node.data = node.data ?? {}
  if (!Array.isArray(node.data.blocks)) node.data.blocks = []
  return node.data.blocks
}
function findNode(payload: any, id: string): any {
  let hit: any = null
  const scan = (nodes: any[]) => {
    for (const n of nodes) {
      if (n.id === id) { hit = n; return }
      const kids = Array.isArray(n.blocks) ? n.blocks : n.data?.blocks
      if (Array.isArray(kids)) scan(kids)
    }
  }
  scan(payload.sections)
  return hit
}
function removeNode(payload: any, id: string): any {
  let removed: any = null
  const scan = (nodes: any[]) => {
    for (let i = 0; i < nodes.length; i++) {
      if (nodes[i].id === id) { removed = nodes.splice(i, 1)[0]; return true }
      const n = nodes[i]
      const kids = Array.isArray(n.blocks) ? n.blocks : n.data?.blocks
      if (Array.isArray(kids) && scan(kids)) return true
    }
    return false
  }
  scan(payload.sections)
  return removed
}
function applyInsert(before: LayoutPayload, params: Record<string, any>): { payload: LayoutPayload; id: string } {
  const p = JSON.parse(JSON.stringify(before))
  const node = { ...params.component, id: `new-${params.component.type}-${++counter}` }
  const parentId = params.target_parent_id
  const target = parentId == null ? p.sections : childList(findNode(p, parentId))
  const at = Math.max(0, Math.min(params.index ?? target.length, target.length))
  target.splice(at, 0, node)
  return { payload: p, id: node.id }
}
function applyMove(before: LayoutPayload, params: Record<string, any>): { payload: LayoutPayload; id: string } {
  const p = JSON.parse(JSON.stringify(before))
  const node = removeNode(p, params.component_id)
  const parentId = params.target_parent_id
  const target = parentId == null ? p.sections : childList(findNode(p, parentId))
  const at = Math.max(0, Math.min(params.index ?? target.length, target.length))
  target.splice(at, 0, node)
  return { payload: p, id: params.component_id }
}

// ---- Pointer-event plumbing ----------------------------------------------
function fire(type: string, x: number, y: number): void {
  const ev = new Event(type, { bubbles: true }) as Event & { clientX: number; clientY: number }
  ev.clientX = x
  ev.clientY = y
  window.dispatchEvent(ev)
}
function down(x: number, y: number): PointerEvent {
  return { clientX: x, clientY: y, pointerId: 1, preventDefault() {}, stopPropagation() {} } as unknown as PointerEvent
}
const flush = () => new Promise((r) => setTimeout(r, 0))

describe('Outline drag — end-to-end (Phase 14.4)', () => {
  let store: ReturnType<typeof useEditorStore>
  let runCommand: ReturnType<typeof vi.fn>
  let zone: OutlineZone | null

  beforeEach(() => {
    setActivePinia(createPinia())
    store = useEditorStore()
    store.metadata = metadata
    store.canvasMode = 'tree'
    zone = null
    // Inject deterministic zone hit-testing (happy-dom elementFromPoint is unreliable).
    outlineDragController.setZoneResolver(() => zone)
    store.attachOutlineDrag()

    runCommand = vi.fn(async (before: LayoutPayload, cmd: string, params: Record<string, unknown>) => {
      const { payload, id } = cmd === 'move-component' ? applyMove(before, params) : applyInsert(before, params)
      return { payload, selection: { selected: id } }
    })
    ;(store as unknown as { client: unknown }).client = { runCommand }
  })

  afterEach(() => {
    store.detachOutlineDrag()
  })

  it('drags a palette Section into an EMPTY outline canvas → inserts at root', async () => {
    store.currentPayload = { schema: 'x', sections: [] }
    store.beginOutlinePaletteDrag(metadata.sections[0], 'section', down(120, 40))
    expect(store.dragActive).toBe(true)

    zone = { parentId: null, index: 0, kind: 'inside' }
    fire('pointermove', 120, 100)
    expect(store.outlineTargetValid).toBe(true)
    expect(store.currentPayload.sections).toHaveLength(0) // no mutation mid-drag

    fire('pointerup', 120, 100)
    await flush()

    expect(runCommand).toHaveBeenCalledTimes(1)
    expect(runCommand.mock.calls[0][1]).toBe('insert-component')
    expect(store.currentPayload.sections).toHaveLength(1)
    expect(store.currentPayload.sections[0].type).toBe('hero')
  })

  it('drags a palette Section BETWEEN two sections → inserts at the correct index', async () => {
    store.currentPayload = {
      schema: 'x',
      sections: [
        { id: 'a', type: 'hero', data: {}, blocks: [] },
        { id: 'b', type: 'hero', data: {}, blocks: [] },
      ],
    }
    store.beginOutlinePaletteDrag(metadata.sections[1], 'section', down(120, 40))

    zone = { parentId: null, index: 1, kind: 'gap' } // between a and b
    fire('pointermove', 120, 120)
    fire('pointerup', 120, 120)
    await flush()

    expect(runCommand).toHaveBeenCalledTimes(1)
    const types = store.currentPayload.sections.map((s) => s.type)
    expect(types).toEqual(['hero', 'cta', 'hero']) // cta landed in the middle
  })

  it('reorders an existing section by dragging it in Outline mode', async () => {
    store.currentPayload = {
      schema: 'x',
      sections: [
        { id: 'a', type: 'hero', data: {}, blocks: [] },
        { id: 'b', type: 'cta', data: {}, blocks: [] },
        { id: 'c', type: 'hero', data: {}, blocks: [] },
      ],
    }
    // Drag section 'a' (index 0) to the end (root zone index 3).
    store.beginOutlineDrag(store.nodeTree[0], down(120, 40))
    zone = { parentId: null, index: 3, kind: 'gap' }
    fire('pointermove', 120, 400)
    // No mutation while moving.
    expect(store.currentPayload.sections.map((s) => s.id)).toEqual(['a', 'b', 'c'])

    fire('pointerup', 120, 400)
    await flush()

    expect(runCommand).toHaveBeenCalledTimes(1)
    expect(runCommand.mock.calls[0][1]).toBe('move-component')
    expect(store.currentPayload.sections.map((s) => s.id)).toEqual(['b', 'c', 'a'])
  })

  it('drags a palette Block into a section → inserts the block', async () => {
    store.currentPayload = { schema: 'x', sections: [{ id: 'sec1', type: 'hero', data: {}, blocks: [] }] }
    store.beginOutlinePaletteDrag(metadata.blocks[0], 'block', down(120, 40))

    zone = { parentId: 'sec1', index: 0, kind: 'inside' }
    fire('pointermove', 120, 120)
    expect(store.currentPayload.sections[0].blocks).toHaveLength(0) // no mutation mid-drag

    fire('pointerup', 120, 120)
    await flush()

    expect(runCommand).toHaveBeenCalledTimes(1)
    const blocks = store.currentPayload.sections[0].blocks
    expect(blocks).toHaveLength(1)
    expect(blocks[0].type).toBe('button')
  })

  it('rejects a palette Block dropped at the root (no command, friendly error)', async () => {
    store.currentPayload = { schema: 'x', sections: [] }
    store.beginOutlinePaletteDrag(metadata.blocks[0], 'block', down(120, 40))

    zone = { parentId: null, index: 0, kind: 'inside' }
    fire('pointermove', 120, 120)
    expect(store.outlineTargetValid).toBe(false)

    fire('pointerup', 120, 120)
    await flush()

    expect(runCommand).not.toHaveBeenCalled()
    expect(store.currentPayload.sections).toHaveLength(0)
    expect(store.commandError).toMatch(/section or container/i)
  })

  it('cancels cleanly on Escape without running a command', async () => {
    store.currentPayload = { schema: 'x', sections: [{ id: 'a', type: 'hero', data: {}, blocks: [] }] }
    store.beginOutlineDrag(store.nodeTree[0], down(120, 40))
    zone = { parentId: null, index: 1, kind: 'gap' }
    fire('pointermove', 120, 120)

    const esc = new Event('keydown') as Event & { key: string; preventDefault: () => void }
    esc.key = 'Escape'
    esc.preventDefault = () => {}
    window.dispatchEvent(esc)
    await flush()

    expect(store.dragActive).toBe(false)
    expect(runCommand).not.toHaveBeenCalled()
  })
})
