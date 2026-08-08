import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { usePatternStore } from '../patternStore'
import { useEditorStore } from '../../stores/editor'
import type { PatternAvailability, PatternManifest } from '../types'

function manifest(over: Partial<PatternManifest>): PatternManifest {
  return {
    id: 'x', name: 'X', slug: 'x', category: 'hero', description: '', tags: [], version: '1.0.0',
    author: '', thumbnail: 'p.svg', thumbnail_url: '/t.svg', keywords: [], difficulty: 'beginner',
    featured: false, premium: false, previewWidth: 1280, previewHeight: 720, ...over,
  }
}

const unavailable: PatternAvailability = {
  available: false,
  missing: { plugins: ['ecommerce'], components: ['product-price'], capabilities: [] },
}

const PATTERNS: PatternManifest[] = [
  manifest({ id: 'ok.one', name: 'OK' }),
  manifest({ id: 'blocked.one', name: 'Blocked', availability: unavailable }),
]

function seed() {
  const editor = useEditorStore()
  ;(editor as unknown as { client: unknown }).client = {
    getPatterns: vi.fn(async () => ({ patterns: PATTERNS, categories: [] })),
    getPatternPayload: vi.fn(async () => ({ manifest: manifest({ id: 'ok.one' }), payload: { version: 1, sections: [{ type: 'hero', data: {} }] } })),
    getResolvedPatternPayload: vi.fn(),
  }
  const execute = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
  return { editor, execute }
}

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
})

describe('patternStore — dependency availability (Phase 25.2)', () => {
  it('reports availability and a human-readable reason', async () => {
    seed()
    const store = usePatternStore()
    await store.load()

    expect(store.isAvailable('ok.one')).toBe(true)
    expect(store.isAvailable('blocked.one')).toBe(false)
    expect(store.unavailableReason('blocked.one')).toContain('plugin: ecommerce')
    expect(store.unavailableReason('blocked.one')).toContain('component: product-price')
    expect(store.unavailableReason('ok.one')).toBe('')
  })

  it('requestInsert refuses an unavailable pattern (no dialog, no dispatch)', async () => {
    const { execute } = seed()
    const store = usePatternStore()
    await store.load()

    store.requestInsert('blocked.one')

    expect(store.variablesFor).toBeNull()
    expect(execute).not.toHaveBeenCalled()
    expect(store.error).toContain('Requires')
  })

  it('insert refuses an unavailable pattern', async () => {
    const { execute } = seed()
    const store = usePatternStore()
    await store.load()

    const ok = await store.insert('blocked.one')

    expect(ok).toBe(false)
    expect(execute).not.toHaveBeenCalled()
  })

  it('still inserts an available pattern', async () => {
    const { execute } = seed()
    const store = usePatternStore()
    await store.load()

    const ok = await store.insert('ok.one')

    expect(ok).toBe(true)
    expect(execute).toHaveBeenCalledWith('insert-pattern', { sections: [{ type: 'hero', data: {} }] })
  })
})
