// Shared fixtures for the drag engine tests. Builds a *nested* marker tree in
// happy-dom so GeometryManager.recomputeHierarchy derives real parent/child
// links (which the DropManager relies on), and a measurer that returns scripted
// client rects so no real layout engine is needed.
import { GeometryManager } from '../../geometry/GeometryManager'
import { HitTest } from '../../geometry/HitTest'
import type { CachedRect } from '../../geometry/GeometryCache'
import type { DragSubject } from '../types'

export function rect(left: number, top: number, width: number, height: number): CachedRect {
  return { x: left, y: top, left, top, width, height, right: left + width, bottom: top + height }
}

export interface MarkerSpec {
  id: string
  kind: string
  type?: string
  rect: CachedRect
  children?: MarkerSpec[]
}

function build(spec: MarkerSpec, parent: HTMLElement, rects: Record<string, CachedRect>): void {
  const el = document.createElement('div')
  el.setAttribute('data-pb-component-id', spec.id)
  el.setAttribute('data-pb-component-kind', spec.kind)
  el.setAttribute('data-pb-component-type', spec.type ?? 'text')
  parent.appendChild(el)
  rects[spec.id] = spec.rect
  for (const child of spec.children ?? []) build(child, el, rects)
}

export interface Scene {
  geometry: GeometryManager
  hitTest: HitTest
  viewport: HTMLElement
}

/** Build a geometry engine over a nested marker tree, measured and refreshed. */
export function scene(tree: MarkerSpec[], viewportRect: CachedRect = rect(0, 0, 400, 600)): Scene {
  document.body.innerHTML = ''
  const viewport = document.createElement('div')
  document.body.appendChild(viewport)

  const rects: Record<string, CachedRect> = {}
  for (const spec of tree) build(spec, viewport, rects)

  const geometry = new GeometryManager((el) => {
    const id = el.getAttribute('data-pb-component-id')
    return (id && rects[id]) || viewportRect
  })
  geometry.setViewport(viewport)
  for (const el of viewport.querySelectorAll<HTMLElement>('[data-pb-component-id]')) {
    geometry.register(el)
  }
  geometry.refresh()

  return { geometry, hitTest: new HitTest(geometry), viewport }
}

export function subject(over: Partial<DragSubject> & Pick<DragSubject, 'id' | 'kind'>): DragSubject {
  return {
    type: 'text',
    label: 'Component',
    icon: null,
    parentId: null,
    index: 0,
    ...over,
  }
}
