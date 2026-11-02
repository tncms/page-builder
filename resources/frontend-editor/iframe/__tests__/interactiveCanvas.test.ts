import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { hydrateInteractive, sanitizeManifest, teardownInteractive } from '../interactiveCanvas'
// @ts-expect-error — plain ES module without type declarations
import { createRuntime } from '../../../frontend/interactive-runtime.js'

const ORIGIN = window.location.origin

function manifest(extra: Array<Record<string, unknown>> = []) {
  return {
    behaviors: ['ecommerce:product-carousel'],
    assets: [
      { handle: 'pbx-runtime', kind: 'module', url: `${ORIGIN}/vendor/page-builder/interactive/runtime.js?v=aaa` },
      { handle: 'pbx.ecommerce.product-carousel.style', kind: 'css', url: `${ORIGIN}/vendor/ecommerce/pb/product-carousel.css?v=bbb` },
      { handle: 'pbx.ecommerce.product-carousel.carousel', kind: 'module', url: `${ORIGIN}/vendor/ecommerce/pb/product-carousel.js?v=ccc` },
      ...extra,
    ],
  }
}

/** Simulate the browser: every appended link/script "loads"; the runtime module installs the real runtime. */
function autoLoad(onAppend?: (el: Element) => void) {
  const original = document.head.appendChild.bind(document.head)
  return vi.spyOn(document.head, 'appendChild').mockImplementation(<T extends Node>(node: T): T => {
    const el = node as unknown as Element
    const res = original(node)
    onAppend?.(el)
    queueMicrotask(() => el.dispatchEvent(new Event('load')))
    return res
  })
}

describe('PB-XREG-3 canvas interactive bridge', () => {
  // An isolated window per test: the real window already carries the (non-configurable) runtime the module import installs.
  let win: Window

  beforeEach(() => {
    // happy-dom would otherwise really fetch inserted <script>/<link> (and fire `error` offline).
    const settings = (window as unknown as { happyDOM?: { settings: Record<string, unknown> } }).happyDOM?.settings
    if (settings) {
      settings.disableJavaScriptFileLoading = true
      settings.disableCSSFileLoading = true
      settings.handleDisabledFileLoadingAsSuccess = true
    }
    document.head.innerHTML = ''
    document.body.innerHTML = '<div id="pb-frontend-canvas"></div>'
    win = { document, location: window.location, console } as unknown as Window
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('keeps only same-origin /vendor/ stylesheet + module entries with valid handles', () => {
    const m = sanitizeManifest(
      manifest([
        { handle: 'pbx.evil.x.a', kind: 'module', url: 'https://cdn.evil.test/vendor/x.js' },
        { handle: 'pbx.evil.x.b', kind: 'module', url: '//evil.test/vendor/x.js' },
        { handle: 'pbx.evil.x.c', kind: 'module', url: 'javascript:alert(1)' },
        { handle: 'pbx.evil.x.d', kind: 'module', url: `${ORIGIN}/storage/x.js` },
        { handle: 'pbx.evil.x.e', kind: 'script', url: `${ORIGIN}/vendor/x.js` },
        { handle: 'EVIL', kind: 'module', url: `${ORIGIN}/vendor/x.js` },
        { handle: 'pbx.evil.x.f', kind: 'css', url: `${ORIGIN}/vendor/x.php` },
      ]),
      ORIGIN,
    )
    expect(m?.assets.map((a) => a.handle)).toEqual([
      'pbx-runtime',
      'pbx.ecommerce.product-carousel.style',
      'pbx.ecommerce.product-carousel.carousel',
    ])
    expect(sanitizeManifest(null, ORIGIN)).toBeNull()
    expect(sanitizeManifest({ assets: 'x' }, ORIGIN)).toEqual({ behaviors: [], assets: [] })
  })

  it('PB-XREG-4: keeps same-origin /themes/ assets of an active-theme behavior, still drops unsafe ones', () => {
    const m = sanitizeManifest(
      {
        behaviors: ['pbx4-voyage-a:hero'],
        assets: [
          { handle: 'pbx-runtime', kind: 'module', url: `${ORIGIN}/vendor/page-builder/interactive/runtime.js?v=aaa` },
          { handle: 'pbx.pbx4-voyage-a.hero.style', kind: 'css', url: `${ORIGIN}/themes/pbx4-voyage-a/page-builder/hero.css?v=bbb` },
          { handle: 'pbx.pbx4-voyage-a.hero.main', kind: 'module', url: `${ORIGIN}/themes/pbx4-voyage-a/page-builder/hero.js?v=ccc` },
          { handle: 'pbx.evil.x.a', kind: 'module', url: 'https://cdn.evil.test/themes/x/hero.js' },
          { handle: 'pbx.evil.x.b', kind: 'module', url: `${ORIGIN}/themes/x/hero.php` },
        ],
      },
      ORIGIN,
    )
    expect(m?.assets.map((a) => a.handle)).toEqual([
      'pbx-runtime',
      'pbx.pbx4-voyage-a.hero.style',
      'pbx.pbx4-voyage-a.hero.main',
    ])
    expect(m?.behaviors).toEqual(['pbx4-voyage-a:hero'])
  })

  it('loads assets once, in order, runtime first, then mounts roots in the editor context', async () => {
    const order: string[] = []
    autoLoad((el) => {
      order.push(el.getAttribute('data-pbx-asset') ?? '')
      if (el.getAttribute('data-pbx-asset') === 'pbx-runtime') {
        Object.defineProperty(win, 'TNPageBuilderInteractive', { value: createRuntime(win), configurable: true })
      }
      if (el.getAttribute('data-pbx-asset') === 'pbx.ecommerce.product-carousel.carousel') {
        ;(win as unknown as { TNPageBuilderInteractive: { define: Function } }).TNPageBuilderInteractive.define(
          'ecommerce:product-carousel',
          { mount: (root: HTMLElement, ctx: { context: string }) => root.setAttribute('data-mounted', ctx.context) },
        )
      }
    })
    const canvas = document.getElementById('pb-frontend-canvas')!
    canvas.innerHTML = '<section class="pbx-root pbx-b-ecommerce--product-carousel"></section>'

    await hydrateInteractive(canvas, manifest(), win)
    expect(order).toEqual(['pbx-runtime', 'pbx.ecommerce.product-carousel.style', 'pbx.ecommerce.product-carousel.carousel'])
    expect(canvas.querySelector('section')!.getAttribute('data-mounted')).toBe('editor')

    // repeated hydration never re-inserts assets
    await hydrateInteractive(canvas, manifest(), win)
    await hydrateInteractive(canvas, manifest(), win)
    expect(document.head.querySelectorAll('[data-pbx-asset]')).toHaveLength(3)
  })

  it('teardown before a swap + hydrate after keeps exactly one mount per root across many re-renders', async () => {
    let live = 0
    autoLoad((el) => {
      if (el.getAttribute('data-pbx-asset') === 'pbx-runtime') {
        const rt = createRuntime(win)
        Object.defineProperty(win, 'TNPageBuilderInteractive', { value: rt, configurable: true })
        rt.define('ecommerce:product-carousel', {
          mount: () => {
            live++
            return () => live--
          },
        })
      }
    })
    const canvas = document.getElementById('pb-frontend-canvas')!
    for (let i = 0; i < 20; i++) {
      teardownInteractive(canvas, win)
      canvas.innerHTML =
        '<section class="pbx-root pbx-b-ecommerce--product-carousel"></section><section class="pbx-root pbx-b-ecommerce--product-carousel"></section>'
      await hydrateInteractive(canvas, manifest(), win)
    }
    expect(live).toBe(2)
  })

  it('a failed runtime load leaves the static canvas untouched and throws nothing', async () => {
    const original = document.head.appendChild.bind(document.head)
    vi.spyOn(document.head, 'appendChild').mockImplementation(<T extends Node>(node: T): T => {
      const res = original(node)
      queueMicrotask(() => (node as unknown as Element).dispatchEvent(new Event('error')))
      return res
    })
    const canvas = document.getElementById('pb-frontend-canvas')!
    canvas.innerHTML = '<section class="pbx-root pbx-b-ecommerce--product-carousel"><ul><li>a</li></ul></section>'
    await expect(hydrateInteractive(canvas, manifest(), win)).resolves.toBe(0)
    expect(canvas.querySelector('li')!.textContent).toBe('a')
  })

  it('no manifest / empty manifest never touches the document', async () => {
    const spy = vi.spyOn(document.head, 'appendChild')
    const canvas = document.getElementById('pb-frontend-canvas')!
    expect(await hydrateInteractive(canvas, undefined, win), win).toBe(0)
    expect(await hydrateInteractive(canvas, { behaviors: [], assets: [] }, win), win).toBe(0)
    expect(spy).not.toHaveBeenCalled()
    teardownInteractive(canvas, win) // runtime absent: no-op
  })
})
