// PB-XREG-3 — Interactive Runtime bridge for the Live Builder canvas (iframe side).
//
// The shell forwards the INERT manifest the authenticated preview endpoints return: the behaviors present in the
// render and the server-derived asset URLs (Page Builder's runtime module first, then provider assets in dependency
// order). Before every canvas swap / component replace the iframe tears interactive roots down; afterwards it loads
// any missing asset (once per document) and mounts the new roots in the `editor` context.
//
// Defence in depth — the manifest never comes from the layout, but it is still validated here: only same-origin
// `/vendor/…` (plugin) or `/themes/…` (PB-XREG-4 active-theme, Core-published) stylesheet/module URLs with a
// well-formed handle are ever inserted; anything else is dropped. A failed
// or slow asset degrades to the static markup and never breaks the canvas.

export interface InteractiveAssetEntry {
  handle: string
  kind: 'css' | 'module'
  url: string
}

export interface InteractiveManifest {
  behaviors: string[]
  assets: InteractiveAssetEntry[]
}

interface RuntimeApi {
  mountWithin(scope: Element, context?: Record<string, unknown>): number
  unmountWithin(scope: Element): number
  sweep(): number
  setDefaultContext(context: Record<string, unknown>): void
}

const HANDLE = /^pbx(-runtime|\.[a-z0-9.-]{3,160})$/
const ATTR = 'data-pbx-asset'
export const RUNTIME_HANDLE = 'pbx-runtime'
const LOAD_TIMEOUT_MS = 8000

const pending = new Map<string, Promise<boolean>>()

/** Keep only entries that are same-origin `/vendor/` or `/themes/` stylesheets/modules with a valid handle. */
export function sanitizeManifest(raw: unknown, origin: string): InteractiveManifest | null {
  if (!raw || typeof raw !== 'object') return null
  const input = raw as { behaviors?: unknown; assets?: unknown }
  const assets: InteractiveAssetEntry[] = []
  for (const item of Array.isArray(input.assets) ? input.assets.slice(0, 64) : []) {
    const a = item as Partial<InteractiveAssetEntry>
    if (typeof a?.handle !== 'string' || !HANDLE.test(a.handle)) continue
    if (a.kind !== 'css' && a.kind !== 'module') continue
    if (typeof a.url !== 'string' || a.url.length > 2048) continue
    let url: URL
    try {
      url = new URL(a.url, origin)
    } catch {
      continue
    }
    if (url.origin !== origin || url.username || url.password || !/\/(vendor|themes)\//.test(url.pathname)) continue
    if (!/\.(css|m?js)$/.test(url.pathname)) continue
    assets.push({ handle: a.handle, kind: a.kind, url: url.href })
  }
  const behaviors = (Array.isArray(input.behaviors) ? input.behaviors : [])
    .filter((b): b is string => typeof b === 'string' && /^[a-z0-9-]+:[a-z0-9-]+$/.test(b))
    .slice(0, 32)
  if (assets.length === 0) return { behaviors: [], assets: [] }
  return { behaviors, assets }
}

function runtimeOf(win: Window): RuntimeApi | null {
  const rt = (win as unknown as { TNPageBuilderInteractive?: RuntimeApi }).TNPageBuilderInteractive
  return rt && typeof rt.mountWithin === 'function' ? rt : null
}

function present(doc: Document, entry: InteractiveAssetEntry): boolean {
  for (const el of Array.from(doc.querySelectorAll(`link[href], script[src]`))) {
    if (el.getAttribute(ATTR) === entry.handle) return true
    const ref = (el as HTMLLinkElement).href || (el as HTMLScriptElement).src
    if (ref === entry.url) return true
  }
  return false
}

function load(doc: Document, entry: InteractiveAssetEntry): Promise<boolean> {
  if (present(doc, entry) && !pending.has(entry.handle)) return Promise.resolve(true)
  const inflight = pending.get(entry.handle)
  if (inflight) return inflight

  const promise = new Promise<boolean>((resolve) => {
    const el =
      entry.kind === 'css'
        ? Object.assign(doc.createElement('link'), { rel: 'stylesheet', href: entry.url })
        : Object.assign(doc.createElement('script'), { type: 'module', src: entry.url })
    el.setAttribute(ATTR, entry.handle)
    const timer = setTimeout(() => resolve(false), LOAD_TIMEOUT_MS)
    el.addEventListener('load', () => {
      clearTimeout(timer)
      resolve(true)
    })
    el.addEventListener('error', () => {
      clearTimeout(timer)
      resolve(false)
    })
    doc.head.appendChild(el)
  }).finally(() => pending.delete(entry.handle))

  pending.set(entry.handle, promise)
  return promise
}

/** Teardown every mounted interactive root inside `scope` (no-op when the runtime was never loaded). */
export function teardownInteractive(scope: Element | null, win: Window = window): void {
  if (!scope) return
  try {
    runtimeOf(win)?.unmountWithin(scope)
  } catch {
    // contained — never break the canvas
  }
}

/**
 * Load the manifest's missing assets in order (runtime first, so a provider module's define() finds it), then mount
 * every root inside `scope` in the editor context. Resolves to the number of newly mounted roots.
 */
export async function hydrateInteractive(scope: Element | null, raw: unknown, win: Window = window): Promise<number> {
  if (!scope) return 0
  const manifest = sanitizeManifest(raw, win.location.origin)
  if (!manifest || manifest.assets.length === 0) return 0
  const doc = win.document

  try {
    for (const entry of manifest.assets) {
      const ok = await load(doc, entry)
      if (entry.handle === RUNTIME_HANDLE) {
        if (!ok) return 0
        runtimeOf(win)?.setDefaultContext({ context: 'editor' })
      }
    }
    const rt = runtimeOf(win)
    if (!rt || !scope.isConnected) return 0
    rt.sweep()
    return rt.mountWithin(scope, { context: 'editor' })
  } catch {
    return 0
  }
}
