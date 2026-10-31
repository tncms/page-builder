// PB-XREG-3 — Page Builder Interactive Runtime 1.0.0 (public frontend + Live Builder canvas).
//
// Hand-authored, dependency-free ES module. `scripts/build-interactive-runtime.mjs` copies it byte-for-byte to
// `public/vendor/page-builder/interactive/runtime.js`; Page Builder loads it ONLY on documents that render at least
// one interactive component (Core AssetRegistry on public pages, the canvas loader in the editor).
//
// Provider modules register a behavior:
//
//   window.TNPageBuilderInteractive.define('ecommerce:product-carousel', {
//     mount(root, context) { …; return () => { /* teardown */ } },
//   })
//
// Roots are the trusted component elements Page Builder marks with `pbx-root pbx-b-{ns}--{local}` (class tokens,
// because the public sanitizer keeps only `class`). Each root mounts at most once; teardown runs exactly once.
// A throwing mount/teardown is contained: the root gets `pbx-failed`, one bounded console warning is emitted
// (behavior id only — never a stack), and every other root keeps working.

const ROOT_CLASS = 'pbx-root'
const FAILED_CLASS = 'pbx-failed'
const BEHAVIOR_PREFIX = 'pbx-b-'
const ID_PATTERN = /^[a-z0-9]+(-[a-z0-9]+)*:[a-z0-9]+(-[a-z0-9]+)*$/

export function behaviorClass(id) {
  return BEHAVIOR_PREFIX + id.replace(':', '--')
}

export function createRuntime(win) {
  const behaviors = new Map() // id -> { mount }
  const mounted = new WeakMap() // root -> { id, teardown }
  const tracked = new Set() // roots currently mounted (for teardown on unmountWithin)
  let defaultContext = { context: 'public' }

  function warn(code, id) {
    try {
      win.console.warn('[pb-interactive] ' + code + ': ' + id)
    } catch {
      // console unavailable — stay silent
    }
  }

  function rootsIn(scope, id) {
    if (!scope || typeof scope.querySelectorAll !== 'function') return []
    const selector = '.' + ROOT_CLASS + (id ? '.' + behaviorClass(id) : '')
    const list = Array.from(scope.querySelectorAll(selector))
    if (scope.nodeType === 1 && scope.matches && scope.matches(selector)) list.unshift(scope)
    return list
  }

  function idOf(root) {
    for (const token of root.classList) {
      if (token.startsWith(BEHAVIOR_PREFIX)) {
        const id = token.slice(BEHAVIOR_PREFIX.length).replace('--', ':')
        if (ID_PATTERN.test(id)) return id
      }
    }
    return null
  }

  function mountRoot(root, context) {
    if (mounted.has(root) || root.classList.contains(FAILED_CLASS)) return false
    const id = idOf(root)
    const behavior = id ? behaviors.get(id) : undefined
    if (!behavior) return false
    let teardown = null
    try {
      const result = behavior.mount(root, Object.freeze({ ...defaultContext, ...context, behavior: id }))
      teardown = typeof result === 'function' ? result : null
    } catch {
      root.classList.add(FAILED_CLASS)
      warn('mount failed', id)
      return false
    }
    mounted.set(root, { id, teardown })
    tracked.add(root)
    return true
  }

  function unmountRoot(root) {
    const entry = mounted.get(root)
    if (!entry) return false
    mounted.delete(root)
    tracked.delete(root)
    if (entry.teardown) {
      try {
        entry.teardown()
      } catch {
        warn('teardown failed', entry.id)
      }
    }
    return true
  }

  const api = Object.freeze({
    version: '1.0.0',
    /** Register a behavior once; later duplicates never override the first. Mounts matching roots already in the document. */
    define(id, definition) {
      if (typeof id !== 'string' || !ID_PATTERN.test(id) || !definition || typeof definition.mount !== 'function') {
        warn('invalid definition', String(id).slice(0, 64))
        return false
      }
      if (behaviors.has(id)) {
        warn('duplicate definition ignored', id)
        return false
      }
      behaviors.set(id, { mount: definition.mount })
      for (const root of rootsIn(win.document, id)) mountRoot(root, {})
      return true
    },
    has(id) {
      return behaviors.has(id)
    },
    /** Mount every defined root inside `scope` (idempotent). Returns the number newly mounted. */
    mountWithin(scope, context = {}) {
      let n = 0
      for (const root of rootsIn(scope, null)) if (mountRoot(root, context)) n++
      return n
    },
    /** Teardown every mounted root inside `scope` (idempotent). Returns the number torn down. */
    unmountWithin(scope) {
      let n = 0
      for (const root of Array.from(tracked)) {
        if (root === scope || (scope && typeof scope.contains === 'function' && scope.contains(root))) {
          if (unmountRoot(root)) n++
        }
      }
      return n
    },
    /** Teardown roots that are no longer connected to the document (after an external DOM replace). */
    sweep() {
      let n = 0
      for (const root of Array.from(tracked)) if (!root.isConnected && unmountRoot(root)) n++
      return n
    },
    setDefaultContext(context) {
      defaultContext = { ...defaultContext, ...context }
    },
    mountedCount() {
      return tracked.size
    },
  })

  return api
}

export function install(win) {
  if (!win) return null
  if (win.TNPageBuilderInteractive) return win.TNPageBuilderInteractive
  const runtime = createRuntime(win)
  Object.defineProperty(win, 'TNPageBuilderInteractive', { value: runtime, writable: false, configurable: false })
  return runtime
}

install(typeof window !== 'undefined' ? window : null)
