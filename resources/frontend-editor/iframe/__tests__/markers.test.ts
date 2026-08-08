import { describe, it, expect, beforeEach } from 'vitest'
import { closestMarker, markerMeta, findMarker } from '../markers'

function setup(): HTMLElement {
  document.body.innerHTML = `
    <div id="pb-frontend-canvas" data-pb-frontend-canvas>
      <div class="pb-editor-render-marker" data-pb-component-id="sec-1" data-pb-component-kind="section" data-pb-component-type="hero">
        <h1 id="title">Hi</h1>
        <div class="pb-editor-render-marker" data-pb-component-id="blk-1" data-pb-component-kind="block" data-pb-component-type="text">
          <p id="para">Body</p>
        </div>
      </div>
    </div>`
  return document.getElementById('pb-frontend-canvas') as HTMLElement
}

describe('markers', () => {
  let root: HTMLElement
  beforeEach(() => {
    root = setup()
  })

  it('resolves the nearest marker for a nested target', () => {
    const para = document.getElementById('para')
    const meta = markerMeta(closestMarker(para, root))
    expect(meta).toEqual({ id: 'blk-1', kind: 'block', type: 'text' })
  })

  it('resolves the section marker for the heading', () => {
    const title = document.getElementById('title')
    const meta = markerMeta(closestMarker(title, root))
    expect(meta?.id).toBe('sec-1')
    expect(meta?.kind).toBe('section')
  })

  it('returns null for a target outside any marker', () => {
    const outside = document.createElement('span')
    document.body.appendChild(outside)
    expect(closestMarker(outside, root)).toBeNull()
    expect(markerMeta(null)).toBeNull()
  })

  it('finds a marker element by id', () => {
    expect(findMarker(root, 'sec-1')?.getAttribute('data-pb-component-type')).toBe('hero')
    expect(findMarker(root, 'missing')).toBeNull()
  })
})
