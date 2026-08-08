// Test helper: build a marker DOM tree with stubbed layout rects (jsdom returns
// zero rects otherwise). Mirrors the FrontendEditorRenderer's nested markers.

export interface Rect {
  top: number
  left: number
  width: number
  height: number
}

export interface MarkerSpec {
  id: string
  kind: 'section' | 'block'
  type: string
  rect: Rect
  children?: MarkerSpec[]
}

function stubRect(el: { getBoundingClientRect: () => DOMRect }, r: Rect): void {
  el.getBoundingClientRect = () =>
    ({
      top: r.top,
      left: r.left,
      width: r.width,
      height: r.height,
      right: r.left + r.width,
      bottom: r.top + r.height,
      x: r.left,
      y: r.top,
      toJSON() {},
    }) as DOMRect
}

function buildMarker(spec: MarkerSpec): HTMLElement {
  const el = document.createElement('div')
  el.setAttribute('data-pb-component-id', spec.id)
  el.setAttribute('data-pb-component-kind', spec.kind)
  el.setAttribute('data-pb-component-type', spec.type)
  stubRect(el, spec.rect)
  for (const child of spec.children ?? []) el.appendChild(buildMarker(child))
  return el
}

export function buildCanvas(specs: MarkerSpec[], canvasRect: Rect): HTMLElement {
  const canvas = document.createElement('div')
  canvas.id = 'pb-frontend-canvas'
  stubRect(canvas, canvasRect)
  for (const spec of specs) canvas.appendChild(buildMarker(spec))
  document.body.appendChild(canvas)
  return canvas
}

/** The standard fixture: 3 sections; s2 has two column containers (colB empty). */
export function standardCanvas(): HTMLElement {
  return buildCanvas(
    [
      { id: 's1', kind: 'section', type: 'hero', rect: { top: 0, left: 0, width: 500, height: 100 } },
      {
        id: 's2',
        kind: 'section',
        type: 'columns',
        rect: { top: 100, left: 0, width: 500, height: 200 },
        children: [
          {
            id: 'colA',
            kind: 'block',
            type: 'column',
            rect: { top: 100, left: 0, width: 250, height: 200 },
            children: [
              { id: 'b1', kind: 'block', type: 'button', rect: { top: 110, left: 10, width: 200, height: 40 } },
            ],
          },
          { id: 'colB', kind: 'block', type: 'column', rect: { top: 100, left: 250, width: 250, height: 200 } },
        ],
      },
      { id: 's3', kind: 'section', type: 'cta', rect: { top: 300, left: 0, width: 500, height: 100 } },
    ],
    { top: 0, left: 0, width: 500, height: 600 },
  )
}

/** The container ids for the standard fixture (sections + column blocks). */
export const STANDARD_CONTAINERS = ['s1', 's2', 's3', 'colA', 'colB']
