import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
// @ts-expect-error — plain ES module without type declarations (hand-authored runtime source)
import { behaviorClass, createRuntime } from '../interactive-runtime.js'

type Runtime = ReturnType<typeof createRuntime>

function root(id: string, extra = ''): HTMLElement {
  const el = document.createElement('section')
  el.className = `pbx-root ${behaviorClass(id)} ${extra}`.trim()
  el.innerHTML = '<ul><li>a</li><li>b</li></ul>'
  return el
}

describe('PB-XREG-3 interactive runtime', () => {
  let rt: Runtime
  let warn: ReturnType<typeof vi.spyOn>

  beforeEach(() => {
    document.body.innerHTML = ''
    rt = createRuntime(window)
    warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
  })

  afterEach(() => {
    warn.mockRestore()
  })

  it('maps a behavior id to its sanitizer-proof class token', () => {
    expect(behaviorClass('ecommerce:product-carousel')).toBe('pbx-b-ecommerce--product-carousel')
  })

  it('define() mounts existing roots once; repeated mountWithin is idempotent', () => {
    const a = root('ecommerce:product-carousel')
    document.body.append(a)
    const mount = vi.fn(() => () => {})
    expect(rt.define('ecommerce:product-carousel', { mount })).toBe(true)
    expect(mount).toHaveBeenCalledTimes(1)
    expect(rt.mountWithin(document.body)).toBe(0)
    expect(rt.mountWithin(document.body)).toBe(0)
    expect(mount).toHaveBeenCalledTimes(1)
    expect(mount.mock.calls[0][1]).toMatchObject({ context: 'public', behavior: 'ecommerce:product-carousel' })
  })

  it('multiple instances mount independently and tear down exactly once each', () => {
    const teardowns: Array<ReturnType<typeof vi.fn>> = []
    rt.define('ecommerce:product-carousel', {
      mount: () => {
        const t = vi.fn()
        teardowns.push(t)
        return t
      },
    })
    const a = root('ecommerce:product-carousel')
    const b = root('ecommerce:product-carousel')
    document.body.append(a, b)
    expect(rt.mountWithin(document.body)).toBe(2)
    expect(rt.mountedCount()).toBe(2)

    expect(rt.unmountWithin(a)).toBe(1)
    expect(teardowns[0]).toHaveBeenCalledTimes(1)
    expect(teardowns[1]).not.toHaveBeenCalled()
    expect(rt.unmountWithin(a)).toBe(0)
    expect(teardowns[0]).toHaveBeenCalledTimes(1)

    // remount after unmount works (editor re-render)
    expect(rt.mountWithin(a)).toBe(1)
    expect(rt.unmountWithin(document.body)).toBe(2)
    expect(rt.mountedCount()).toBe(0)
  })

  it('repeated re-render cycles never accumulate listeners', () => {
    let listeners = 0
    rt.define('ecommerce:product-carousel', {
      mount: (el: HTMLElement) => {
        const h = () => {}
        el.addEventListener('click', h)
        listeners++
        return () => {
          el.removeEventListener('click', h)
          listeners--
        }
      },
    })
    const host = document.createElement('div')
    document.body.append(host)
    for (let i = 0; i < 25; i++) {
      rt.unmountWithin(host)
      host.innerHTML = ''
      host.append(root('ecommerce:product-carousel'), root('ecommerce:product-carousel'))
      rt.mountWithin(host)
    }
    expect(listeners).toBe(2)
    expect(rt.mountedCount()).toBe(2)
  })

  it('a throwing mount is contained to its root with a bounded diagnostic', () => {
    rt.define('bad-plugin:widget', {
      mount: () => {
        throw new Error('secret /var/www/path stack')
      },
    })
    const good = vi.fn()
    rt.define('ecommerce:product-carousel', { mount: good })
    const bad = root('bad-plugin:widget')
    const ok = root('ecommerce:product-carousel')
    document.body.append(bad, ok)
    rt.mountWithin(document.body)

    expect(bad.classList.contains('pbx-failed')).toBe(true)
    expect(good).toHaveBeenCalledTimes(1)
    expect(warn).toHaveBeenCalledWith('[pb-interactive] mount failed: bad-plugin:widget')
    expect(JSON.stringify(warn.mock.calls)).not.toContain('/var/www')
    // a failed root is never retried
    rt.mountWithin(document.body)
    expect(warn.mock.calls.filter((c) => String(c[0]).includes('mount failed'))).toHaveLength(1)
  })

  it('a throwing teardown is contained and the root is still released', () => {
    rt.define('ecommerce:product-carousel', { mount: () => () => { throw new Error('x') } })
    const a = root('ecommerce:product-carousel')
    document.body.append(a)
    rt.mountWithin(document.body)
    expect(rt.unmountWithin(document.body)).toBe(1)
    expect(rt.mountedCount()).toBe(0)
    expect(warn).toHaveBeenCalledWith('[pb-interactive] teardown failed: ecommerce:product-carousel')
  })

  it('duplicate or malformed definitions never override the first', () => {
    const first = vi.fn()
    const second = vi.fn()
    expect(rt.define('ecommerce:product-carousel', { mount: first })).toBe(true)
    expect(rt.define('ecommerce:product-carousel', { mount: second })).toBe(false)
    expect(rt.define('Bad Id', { mount: second })).toBe(false)
    expect(rt.define('ecommerce:x', {} as never)).toBe(false)
    document.body.append(root('ecommerce:product-carousel'))
    rt.mountWithin(document.body)
    expect(first).toHaveBeenCalledTimes(1)
    expect(second).not.toHaveBeenCalled()
  })

  it('roots without a defined behavior, forged class tokens, or without pbx-root are ignored', () => {
    const mount = vi.fn()
    rt.define('ecommerce:product-carousel', { mount })
    const noRoot = document.createElement('div')
    noRoot.className = behaviorClass('ecommerce:product-carousel') // missing pbx-root
    const forged = document.createElement('div')
    forged.className = 'pbx-root pbx-b-../../evil'
    document.body.append(noRoot, forged, root('other:thing'))
    expect(rt.mountWithin(document.body)).toBe(0)
    expect(mount).not.toHaveBeenCalled()
  })

  it('sweep() tears down roots removed by an external DOM replace', () => {
    const t = vi.fn()
    rt.define('ecommerce:product-carousel', { mount: () => t })
    const a = root('ecommerce:product-carousel')
    document.body.append(a)
    rt.mountWithin(document.body)
    document.body.innerHTML = ''
    expect(rt.sweep()).toBe(1)
    expect(t).toHaveBeenCalledTimes(1)
  })

  it('passes the editor context through when the canvas mounts', () => {
    const mount = vi.fn()
    rt.define('ecommerce:product-carousel', { mount })
    const a = root('ecommerce:product-carousel')
    document.body.append(a)
    rt.unmountWithin(document.body)
    rt.setDefaultContext({ context: 'editor' })
    // first mount happened in define() with public context; force a remount under editor context
    rt.unmountWithin(a)
    const b = root('ecommerce:product-carousel')
    document.body.append(b)
    rt.mountWithin(document.body)
    expect(mount.mock.calls.at(-1)?.[1]).toMatchObject({ context: 'editor' })
  })
})
