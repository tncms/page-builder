import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useResourceBrowserStore } from '../resourceBrowserStore'
import type { ResourceBrowserItem, ResourceCatalog, ResourceMaterializer } from '../types'

// PB-TEMPLATE-REGISTRY-2-C — the store orchestrates preview/insert/apply state over the
// R2-B catalog. Selecting/previewing NEVER mutates (INV-R2C-01/02); actions delegate to
// the injected materializer (never reimplement insertion/import); a double action fires
// ONE mutation; Template replacement is a two-stage confirm whose Cancel sends zero
// request; a failed/stale action preserves local state and never shows a false success.

function item(over: Partial<ResourceBrowserItem>): ResourceBrowserItem {
  return {
    rid: over.rid ?? 'builtin:section:x',
    kind: over.kind ?? 'pattern',
    source: over.source ?? 'builtin',
    category: over.category ?? 'hero',
    tags: over.tags ?? [],
    name: over.name ?? 'X',
    description: over.description ?? null,
    preview: over.preview ?? null,
    available: over.available ?? true,
    locked: over.locked ?? false,
    requiredCapability: over.requiredCapability ?? null,
  }
}

function catalog(): ResourceCatalog {
  return {
    patterns: [
      item({ rid: 'builtin:section:hero', name: 'Hero' }),
      item({ rid: 'builtin:section:pro', name: 'Pro', available: true, locked: true, requiredCapability: 'pro' }),
      item({ rid: 'builtin:section:gone', name: 'Gone', available: false }),
    ],
    templates: [item({ rid: 'builtin:page:company', kind: 'template', name: 'Company' })],
  }
}

function scene() {
  setActivePinia(createPinia())
  const store = useResourceBrowserStore()
  store.setCatalog(catalog())
  store.openBrowser('pattern') // the user acts with the browser open
  return store
}

function materializer(over: Partial<ResourceMaterializer> = {}): ResourceMaterializer {
  return {
    insertPattern: vi.fn(async () => ({ status: 'inserted' as const })),
    applyTemplate: vi.fn(async () => ({ status: 'applied' as const })),
    ...over,
  }
}

beforeEach(() => setActivePinia(createPinia()))

describe('selection opens a non-mutating detail/preview', () => {
  it('selecting a resource opens the detail pane and never calls the materializer', () => {
    const store = scene()
    const m = materializer()
    store.bindMaterializer(m)
    store.select('builtin:section:hero')
    expect(store.detailOpen).toBe(true)
    expect(store.selectedItem?.rid).toBe('builtin:section:hero')
    expect(m.insertPattern).not.toHaveBeenCalled()
    expect(m.applyTemplate).not.toHaveBeenCalled()
  })

  it('closing the detail clears selection, pending template, and action error', () => {
    const store = scene()
    store.select('builtin:section:hero')
    store.closeDetail()
    expect(store.detailOpen).toBe(false)
    expect(store.selectedRid).toBeNull()
    expect(store.pendingTemplate).toBeNull()
    expect(store.actionError).toBeNull()
  })
})

describe('pattern insertion delegates once', () => {
  it('inserts an available pattern via the materializer and closes the browser', async () => {
    const store = scene()
    const m = materializer()
    store.bindMaterializer(m)
    store.select('builtin:section:hero')

    await store.insertSelected()

    expect(m.insertPattern).toHaveBeenCalledTimes(1)
    expect(m.insertPattern).toHaveBeenCalledWith('builtin:section:hero')
    expect(store.open).toBe(false) // one documented rule: success closes the browser
    expect(store.actionError).toBeNull()
  })

  it('guards double-submit: two rapid inserts fire ONE mutation', async () => {
    let resolve!: () => void
    const gate = new Promise<void>((r) => { resolve = r })
    const m = materializer({ insertPattern: vi.fn(async () => { await gate; return { status: 'inserted' as const } }) })
    const store = scene()
    store.bindMaterializer(m)
    store.select('builtin:section:hero')

    const a = store.insertSelected()
    const b = store.insertSelected()
    resolve()
    await Promise.all([a, b])

    expect(m.insertPattern).toHaveBeenCalledTimes(1)
  })

  it('refuses to insert an unavailable resource — zero mutation, safe error', async () => {
    const store = scene()
    const m = materializer()
    store.bindMaterializer(m)
    store.select('builtin:section:gone')

    await store.insertSelected()

    expect(m.insertPattern).not.toHaveBeenCalled()
    expect(store.actionError?.code).toBe('unavailable')
    expect(store.open).toBe(true) // browser stays; nothing changed
  })

  it('refuses to insert a locked resource — zero mutation', async () => {
    const store = scene()
    const m = materializer()
    store.bindMaterializer(m)
    store.select('builtin:section:pro')
    await store.insertSelected()
    expect(m.insertPattern).not.toHaveBeenCalled()
    expect(store.actionError?.code).toBe('unavailable')
  })

  it('a failed insert preserves selection + browser and shows a typed error', async () => {
    const store = scene()
    const m = materializer({ insertPattern: vi.fn(async () => ({ status: 'failed' as const, message: 'boom' })) })
    store.bindMaterializer(m)
    store.select('builtin:section:hero')

    await store.insertSelected()

    expect(store.open).toBe(true)
    expect(store.selectedRid).toBe('builtin:section:hero')
    expect(store.actionError?.code).toBe('failed')
  })
})

describe('template replacement is a two-stage confirmation', () => {
  it('requesting a template opens stage-2 confirmation and sends no mutation request', () => {
    const store = scene()
    const m = materializer()
    store.bindMaterializer(m)
    store.setKind('template')
    store.select('builtin:page:company')

    store.requestTemplate()

    expect(store.pendingTemplate).toBe('builtin:page:company')
    expect(store.isTemplateConfirmOpen).toBe(true)
    expect(m.applyTemplate).not.toHaveBeenCalled()
  })

  it('Cancel closes the confirmation with ZERO apply request and preserves the draft', () => {
    const store = scene()
    const m = materializer()
    store.bindMaterializer(m)
    store.setKind('template')
    store.select('builtin:page:company')
    store.requestTemplate()

    store.cancelTemplate()

    expect(store.pendingTemplate).toBeNull()
    expect(store.isTemplateConfirmOpen).toBe(false)
    expect(m.applyTemplate).not.toHaveBeenCalled()
  })

  it('Confirm applies to the draft exactly once and closes on success', async () => {
    const store = scene()
    const m = materializer()
    store.bindMaterializer(m)
    store.setKind('template')
    store.select('builtin:page:company')
    store.requestTemplate()

    await store.confirmTemplate()

    expect(m.applyTemplate).toHaveBeenCalledTimes(1)
    expect(m.applyTemplate).toHaveBeenCalledWith('builtin:page:company')
    expect(store.pendingTemplate).toBeNull()
    expect(store.open).toBe(false)
  })

  it('guards double confirm: two rapid confirms fire ONE apply', async () => {
    let resolve!: () => void
    const gate = new Promise<void>((r) => { resolve = r })
    const m = materializer({ applyTemplate: vi.fn(async () => { await gate; return { status: 'applied' as const } }) })
    const store = scene()
    store.bindMaterializer(m)
    store.setKind('template')
    store.select('builtin:page:company')
    store.requestTemplate()

    const a = store.confirmTemplate()
    const b = store.confirmTemplate()
    resolve()
    await Promise.all([a, b])

    expect(m.applyTemplate).toHaveBeenCalledTimes(1)
  })

  it('a stale/conflict apply preserves local work and surfaces reconciliation, no false success', async () => {
    const store = scene()
    const m = materializer({ applyTemplate: vi.fn(async () => ({ status: 'conflict' as const, message: 'The draft changed since the preview.' })) })
    store.bindMaterializer(m)
    store.setKind('template')
    store.select('builtin:page:company')
    store.requestTemplate()

    await store.confirmTemplate()

    expect(store.actionError?.code).toBe('stale')
    expect(store.open).toBe(true) // not auto-closed / not reloaded
    expect(store.pendingTemplate).toBeNull() // confirmation layer closed
  })

  it('requesting a template while a PATTERN is selected does nothing (kind guard)', () => {
    const store = scene()
    store.select('builtin:section:hero') // a pattern
    store.requestTemplate()
    expect(store.pendingTemplate).toBeNull()
  })
})
