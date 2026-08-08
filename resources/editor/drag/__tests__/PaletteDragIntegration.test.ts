import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../../stores/editor'
import { geometryManager } from '../../geometry'
import type { CachedRect } from '../../geometry/GeometryCache'
import type { LayoutPayload, EditorMetadata } from '../../types'

function rect(left: number, top: number, width: number, height: number): CachedRect {
  return { x: left, y: top, left, top, width, height, right: left + width, bottom: top + height }
}

const metadata = {
  version: '1',
  sections: [{ type: 'hero', label: 'Hero', defaults: { heading: 'Hi' } }],
  blocks: [{ type: 'button', label: 'Button', defaults: { text: 'Go' } }],
  tokens: {},
} as unknown as EditorMetadata

// Faithful insert: regenerate a readable id and splice into the target parent.
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
function applyInsert(before: LayoutPayload, params: Record<string, any>): { payload: LayoutPayload; id: string } {
  const p = JSON.parse(JSON.stringify(before))
  const node = { ...params.component, id: `new-${params.component.type}-${++counter}` }
  const parentId = params.target_parent_id
  const target = parentId == null ? p.sections : childList(findNode(p, parentId))
  const at = Math.max(0, Math.min(params.index ?? target.length, target.length))
  target.splice(at, 0, node)
  return { payload: p, id: node.id }
}

function marker(id: string, kind: string, type: string): HTMLElement {
  const el = document.createElement('div')
  el.setAttribute('data-pb-component-id', id)
  el.setAttribute('data-pb-component-kind', kind)
  el.setAttribute('data-pb-component-type', type)
  return el
}

function fire(type: string, x: number, y: number): void {
  const ev = new Event(type, { bubbles: true }) as Event & { clientX: number; clientY: number }
  ev.clientX = x
  ev.clientY = y
  window.dispatchEvent(ev)
}
function down(x: number, y: number): PointerEvent {
  return { clientX: x, clientY: y, preventDefault() {}, stopPropagation() {} } as unknown as PointerEvent
}
const flush = () => new Promise((r) => setTimeout(r, 0))

describe('Palette insert drag — end-to-end (Phase 14.2)', () => {
  let store: ReturnType<typeof useEditorStore>
  let runCommand: ReturnType<typeof vi.fn>

  function boot(sections: unknown[], rects: Record<string, CachedRect>, buildHost: (host: HTMLElement) => void) {
    store.currentPayload = { version: 1, sections } as unknown as LayoutPayload
    geometryManager.setMeasurer((el) => rects[el.getAttribute('data-pb-component-id') ?? ''] ?? rect(0, 0, 400, 600))
    document.body.innerHTML = ''
    const viewport = document.createElement('div')
    const host = document.createElement('div')
    viewport.appendChild(host)
    document.body.appendChild(viewport)
    buildHost(host)
    store.attachGeometry(viewport, host)
    geometryManager.refresh()
    store.attachDrag(viewport)
  }

  beforeEach(() => {
    // Simulate the browser: structuredClone throws DataCloneError on reactive
    // proxies. The clone path must NOT depend on it (Phase 14.2 hotfix).
    vi.stubGlobal('structuredClone', () => {
      throw new Error("Failed to execute 'structuredClone': could not be cloned.")
    })
    setActivePinia(createPinia())
    store = useEditorStore()
    store.metadata = metadata
    store.canvasMode = 'visual'
    runCommand = vi.fn(async (before: LayoutPayload, _cmd: string, params: Record<string, unknown>) => {
      const { payload, id } = applyInsert(before, params)
      return { payload, selection: { selected: id } }
    })
    ;(store as unknown as { client: unknown }).client = { runCommand }
  })

  it('drags a Section from the palette into an EMPTY canvas → inserts at root', async () => {
    boot([], {}, () => {}) // empty host, no markers
    store.beginPaletteDrag(metadata.sections[0], 'section', down(200, 100))
    expect(store.dragActive).toBe(true)

    fire('pointermove', 200, 200) // over the empty viewport → root drop
    expect(store.dragResolution?.valid).toBe(true)
    expect(store.dragResolution?.parentId).toBeNull()
    // Payload must NOT change until the drop.
    expect((store.currentPayload as any).sections).toHaveLength(0)

    fire('pointerup', 200, 200)
    await flush()

    expect(runCommand).toHaveBeenCalledTimes(1)
    expect(runCommand.mock.calls[0][1]).toBe('insert-component')
    const sections = (store.currentPayload as any).sections
    expect(sections).toHaveLength(1)
    expect(sections[0].type).toBe('hero')
    expect(store.selectedComponentId).toBe(sections[0].id) // new section selected
  })

  it('drags a Block from the palette INTO a section', async () => {
    boot(
      [{ id: 'sec1', type: 'hero', data: {}, blocks: [] }],
      { sec1: rect(0, 0, 400, 200) },
      (host) => host.appendChild(marker('sec1', 'section', 'hero')),
    )

    store.beginPaletteDrag(metadata.blocks[0], 'block', down(10, 10))
    fire('pointermove', 200, 100) // inside sec1
    // No mutation mid-drag.
    expect((store.currentPayload as any).sections[0].blocks).toHaveLength(0)

    fire('pointerup', 200, 100)
    await flush()

    expect(runCommand).toHaveBeenCalledTimes(1)
    const blocks = (store.currentPayload as any).sections[0].blocks
    expect(blocks).toHaveLength(1)
    expect(blocks[0].type).toBe('button')
  })

  it('rejects dropping a palette Block into an empty root (no section exists)', async () => {
    boot([], {}, () => {})
    store.beginPaletteDrag(metadata.blocks[0], 'block', down(10, 10))
    fire('pointermove', 200, 200) // empty root — invalid for a block
    expect(store.dragResolution?.valid).toBe(false)

    fire('pointerup', 200, 200)
    await flush()
    expect(runCommand).not.toHaveBeenCalled()
    expect((store.currentPayload as any).sections).toHaveLength(0)
  })
})
