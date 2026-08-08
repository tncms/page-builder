// Seeds the singleton geometry engine (the one the store's overlay getters
// read) with scripted rects, so overlay components can be mounted and asserted
// without a real layout engine.
import { setActivePinia, createPinia } from 'pinia'
import { geometryManager } from '../../../geometry'
import type { CachedRect } from '../../../geometry/GeometryCache'
import { useEditorStore } from '../../../stores/editor'

function rect(left: number, top: number, width: number, height: number): CachedRect {
  return { x: left, y: top, left, top, width, height, right: left + width, bottom: top + height }
}

export interface SeededMarker {
  id: string
  kind?: string
  type?: string
  rect: CachedRect
}

export function seedGeometry(markers: SeededMarker[], viewport = rect(0, 0, 800, 600)) {
  geometryManager.clear()
  document.body.innerHTML = ''
  const viewportEl = document.createElement('div')
  document.body.appendChild(viewportEl)

  const rects: Record<string, CachedRect> = {}
  for (const mk of markers) {
    const el = document.createElement('div')
    el.setAttribute('data-pb-component-id', mk.id)
    el.setAttribute('data-pb-component-kind', mk.kind ?? 'block')
    el.setAttribute('data-pb-component-type', mk.type ?? 'text')
    document.body.appendChild(el)
    rects[mk.id] = mk.rect
    geometryManager.register(el)
  }

  geometryManager.setMeasurer((el) => {
    const id = el.getAttribute('data-pb-component-id')
    return (id && rects[id]) || viewport
  })
  geometryManager.setViewport(viewportEl)
  geometryManager.refresh()
}

export function freshStore() {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.geometryVersion = geometryManager.currentVersion()
  return store
}

export { rect }
