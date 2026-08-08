import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../../stores/editor'
import { geometryManager, overlayManager } from '../../geometry'
import type { CachedRect } from '../../geometry/GeometryCache'
import type { LayoutPayload, EditorMetadata } from '../../types'

// ------------------------------------------------------------------ fixtures
// A page that mirrors the real renderer's markers: sections and blocks only —
// there is NO 'container' kind (BlockInstance::kind() always returns 'block').
// colA holds two items; colB is an EMPTY container block.
//
//   sec1
//     colA (columns) [ item1, item2 ]
//     colB (columns) []            <- empty container
//   sec2
type Rects = Record<string, CachedRect>

function rect(left: number, top: number, width: number, height: number): CachedRect {
  return { x: left, y: top, left, top, width, height, right: left + width, bottom: top + height }
}

const RECTS: Rects = {
  sec1: rect(0, 0, 400, 300),
  colA: rect(10, 10, 380, 130),
  item1: rect(20, 20, 360, 50),
  item2: rect(20, 80, 360, 50),
  colB: rect(10, 150, 380, 120),
  sec2: rect(0, 320, 400, 150),
}

function payload(): LayoutPayload {
  return {
    version: 1,
    sections: [
      {
        id: 'sec1',
        type: 'hero',
        data: {},
        blocks: [
          {
            id: 'colA',
            type: 'columns',
            data: {
              blocks: [
                { id: 'item1', type: 'button', data: {} },
                { id: 'item2', type: 'text', data: {} },
              ],
            },
          },
          { id: 'colB', type: 'columns', data: { blocks: [] } },
        ],
      },
      { id: 'sec2', type: 'cta', data: {}, blocks: [] },
    ],
  } as unknown as LayoutPayload
}

const metadata = {
  version: '1',
  sections: [{ type: 'hero', label: 'Hero' }, { type: 'cta', label: 'CTA' }],
  blocks: [{ type: 'columns', label: 'Columns' }, { type: 'button', label: 'Button' }, { type: 'text', label: 'Text' }],
  tokens: {},
} as unknown as EditorMetadata

// ------------------------------------------------------------- backend double
// Faithful remove-then-insert, matching LayoutMutator semantics, so a reorder is
// observable in the returned payload.
function childrenOf(node: any): any[] | null {
  if (Array.isArray(node.blocks)) return node.blocks
  if (node.data && Array.isArray(node.data.blocks)) return node.data.blocks
  return null
}

function findParentList(payload: any, id: string): { list: any[]; index: number } | null {
  const scan = (nodes: any[]): { list: any[]; index: number } | null => {
    for (let i = 0; i < nodes.length; i++) {
      if (nodes[i].id === id) return { list: nodes, index: i }
      const kids = childrenOf(nodes[i])
      if (kids) {
        const hit = scan(kids)
        if (hit) return hit
      }
    }
    return null
  }
  return scan(payload.sections)
}

function findNode(payload: any, id: string): any {
  if (id === null) return null
  let found: any = null
  const scan = (nodes: any[]) => {
    for (const n of nodes) {
      if (n.id === id) { found = n; return }
      const kids = childrenOf(n)
      if (kids) scan(kids)
    }
  }
  scan(payload.sections)
  return found
}

function applyMove(before: LayoutPayload, params: Record<string, any>): LayoutPayload {
  const p = JSON.parse(JSON.stringify(before))
  const loc = findParentList(p, params.component_id)
  if (!loc) return p
  const [node] = loc.list.splice(loc.index, 1)

  const parentId = params.target_parent_id
  let target: any[]
  if (parentId == null) {
    target = p.sections
  } else {
    const parent = findNode(p, parentId)
    if (!parent) return p
    if (Array.isArray(parent.blocks)) target = parent.blocks
    else {
      parent.data = parent.data ?? {}
      if (!Array.isArray(parent.data.blocks)) parent.data.blocks = []
      target = parent.data.blocks
    }
  }
  const at = Math.max(0, Math.min(params.index ?? target.length, target.length))
  target.splice(at, 0, node)
  return p
}

// --------------------------------------------------------------- DOM + engine
function marker(id: string, kind: string, type: string): HTMLElement {
  const el = document.createElement('div')
  el.setAttribute('data-pb-component-id', id)
  el.setAttribute('data-pb-component-kind', kind) // section | block (never container)
  el.setAttribute('data-pb-component-type', type)
  return el
}

function buildDom(): { viewport: HTMLElement; host: HTMLElement } {
  document.body.innerHTML = ''
  const viewport = document.createElement('div')
  const host = document.createElement('div')
  viewport.appendChild(host)
  document.body.appendChild(viewport)

  const sec1 = marker('sec1', 'section', 'hero')
  const colA = marker('colA', 'block', 'columns')
  colA.appendChild(marker('item1', 'block', 'button'))
  colA.appendChild(marker('item2', 'block', 'text'))
  sec1.appendChild(colA)
  sec1.appendChild(marker('colB', 'block', 'columns'))
  host.appendChild(sec1)
  host.appendChild(marker('sec2', 'section', 'cta'))
  return { viewport, host }
}

function fire(type: string, x: number, y: number): void {
  const ev = new Event(type, { bubbles: true }) as Event & { clientX: number; clientY: number }
  ev.clientX = x
  ev.clientY = y
  window.dispatchEvent(ev)
}

function pointerDown(x: number, y: number): PointerEvent {
  return { clientX: x, clientY: y, preventDefault() {}, stopPropagation() {} } as unknown as PointerEvent
}

describe('Drag engine — end-to-end pointer simulation (Phase 14.1)', () => {
  let store: ReturnType<typeof useEditorStore>
  let runCommand: ReturnType<typeof vi.fn>

  beforeEach(() => {
    // Simulate the browser: structuredClone throws DataCloneError on reactive
    // proxies. The clone path must NOT depend on it (Phase 14.2 hotfix).
    vi.stubGlobal('structuredClone', () => {
      throw new Error("Failed to execute 'structuredClone': could not be cloned.")
    })
    setActivePinia(createPinia())
    store = useEditorStore()
    store.currentPayload = payload()
    store.metadata = metadata
    store.canvasMode = 'visual'

    runCommand = vi.fn(async (before: LayoutPayload, _cmd: string, params: Record<string, unknown>) => ({
      payload: applyMove(before, params),
      selection: { selected: null },
    }))
    ;(store as unknown as { client: unknown }).client = { runCommand }

    // Feed scripted rects into the shared geometry engine and wire the canvas.
    geometryManager.setMeasurer((el) => RECTS[el.getAttribute('data-pb-component-id') ?? ''] ?? rect(0, 0, 400, 600))
    const { viewport, host } = buildDom()
    store.attachGeometry(viewport, host)
    geometryManager.refresh()
    store.attachDrag(viewport)
  })

  // Flush the fire-and-forget finishDrag → executeCommand promise chain.
  const flush = () => new Promise((r) => setTimeout(r, 0))

  async function drag(id: string, from: [number, number], to: [number, number]) {
    store.beginDrag(id, pointerDown(from[0], from[1]))
    fire('pointermove', to[0], to[1])
    fire('pointerup', to[0], to[1])
    await flush()
  }

  const order = (parentId: string | null): string[] => {
    const p = store.currentPayload as any
    if (parentId === null) return p.sections.map((s: any) => s.id)
    return (findNode(p, parentId)?.data?.blocks ?? findNode(p, parentId)?.blocks ?? []).map((n: any) => n.id)
  }

  it('step 8/9: drag becomes active and DropManager resolves a target on move', () => {
    store.beginDrag('item1', pointerDown(200, 40))
    expect(store.dragActive).toBe(true)
    fire('pointermove', 200, 120) // over item2
    expect(store.dragTarget).toBe('item2')
    expect(store.dragEdge).not.toBeNull()
    fire('pointerup', 200, 120)
    expect(store.dragActive).toBe(false)
  })

  it('case A: drag a root section below another section reorders the sections', async () => {
    await drag('sec1', [200, 20], [200, 440]) // sec1 → after sec2
    expect(runCommand).toHaveBeenCalledTimes(1)
    expect(order(null)).toEqual(['sec2', 'sec1'])
    expect(store.selectedComponentId).toBe('sec1') // selection preserved (step 15)
  })

  it('case C: reorder a block within its container', async () => {
    await drag('item1', [200, 40], [200, 120]) // item1 → after item2 inside colA
    expect(order('colA')).toEqual(['item2', 'item1'])
  })

  it('case B/D: drag a block INTO an empty container block', async () => {
    await drag('item1', [200, 40], [200, 210]) // item1 → inside empty colB
    expect(runCommand).toHaveBeenCalledTimes(1)
    expect(order('colB')).toEqual(['item1']) // landed inside colB
    expect(order('colA')).toEqual(['item2']) // and left colA
  })
})
