import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { usePatternStore } from '../patternStore'
import { useEditorStore } from '../../stores/editor'
import type { PatternManifest } from '../types'

function manifest(over: Partial<PatternManifest>): PatternManifest {
  return {
    id: 'x', name: 'X', slug: 'x', category: 'hero', description: '', tags: [], version: '1.0.0',
    author: '', thumbnail: 'preview.svg', thumbnail_url: '/t.svg', keywords: [], difficulty: 'beginner',
    featured: false, premium: false, previewWidth: 1280, previewHeight: 720, ...over,
  }
}

const PATTERNS: PatternManifest[] = [
  manifest({ id: 'hero.startup', name: 'Startup Hero', category: 'hero', tags: ['saas'], featured: true }),
  manifest({ id: 'hero.centered', name: 'Centered Hero', category: 'hero' }),
  manifest({ id: 'cta.trial', name: 'Free Trial CTA', category: 'cta', featured: true, tags: ['trial'] }),
  manifest({ id: 'pro.kit', name: 'Pro Kit', category: 'features', premium: true }),
]

function seedEditorClient(payloadSections: Array<Record<string, unknown>> = [{ type: 'hero', data: {} }]) {
  const editor = useEditorStore()
  const getPatternPayload = vi.fn(async (id: string) => ({ manifest: manifest({ id }), payload: { version: 1, sections: payloadSections } }))
  ;(editor as unknown as { client: unknown }).client = {
    getPatterns: vi.fn(async () => ({ patterns: PATTERNS, categories: [{ key: 'hero', name: 'Hero', icon: '', description: '', order: 10, count: 2 }] })),
    getPatternPayload,
  }
  const execute = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
  return { editor, execute, getPatternPayload }
}

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
})

describe('patternStore — loading + search + filters', () => {
  it('loads manifests + categories once', async () => {
    const { editor } = seedEditorClient()
    const store = usePatternStore()
    await store.load()
    await store.load() // second call is a no-op
    expect(store.loaded).toBe(true)
    expect(store.manifests).toHaveLength(4)
    expect((editor.client as any).getPatterns).toHaveBeenCalledTimes(1)
  })

  it('searches across name/tags/category', async () => {
    seedEditorClient()
    const store = usePatternStore()
    await store.load()
    store.setQuery('startup')
    expect(store.results.map((p) => p.id)).toEqual(['hero.startup'])
    store.setQuery('saas')
    expect(store.results.map((p) => p.id)).toEqual(['hero.startup'])
  })

  it('filters by category, featured, free and premium', async () => {
    seedEditorClient()
    const store = usePatternStore()
    await store.load()

    store.setCategory('hero')
    expect(store.results).toHaveLength(2)
    store.setCategory(null)

    store.setFilterMode('featured')
    expect(store.results.map((p) => p.id).sort()).toEqual(['cta.trial', 'hero.startup'])
    store.setFilterMode('premium')
    expect(store.results.map((p) => p.id)).toEqual(['pro.kit'])
    store.setFilterMode('free')
    expect(store.results.map((p) => p.id)).not.toContain('pro.kit')
  })
})

describe('patternStore — favorites + recent (localStorage)', () => {
  it('toggles + persists favorites', async () => {
    seedEditorClient()
    const store = usePatternStore()
    await store.load()
    store.toggleFavorite('hero.startup')
    expect(store.isFavorite('hero.startup')).toBe(true)
    expect(JSON.parse(localStorage.getItem('pb.patterns.favorites')!)).toEqual(['hero.startup'])

    store.setFilterMode('favorites')
    expect(store.results.map((p) => p.id)).toEqual(['hero.startup'])

    store.toggleFavorite('hero.startup')
    expect(store.isFavorite('hero.startup')).toBe(false)
  })

  it('records recent on insert, newest-first, capped at 20', async () => {
    seedEditorClient()
    const store = usePatternStore()
    await store.load()
    await store.insert('hero.centered')
    await store.insert('hero.startup')
    await store.insert('hero.centered') // moves to front, no dupe
    expect(store.recent.slice(0, 2)).toEqual(['hero.centered', 'hero.startup'])
    expect(store.recent.filter((r) => r === 'hero.centered')).toHaveLength(1)
  })
})

describe('patternStore — insert via command flow', () => {
  it('fetches the payload and dispatches ONE insert-pattern command', async () => {
    const { execute, getPatternPayload } = seedEditorClient([{ type: 'hero', data: {} }, { type: 'cta', data: {} }])
    const store = usePatternStore()
    await store.load()

    const ok = await store.insert('hero.split')
    expect(ok).toBe(true)
    expect(getPatternPayload).toHaveBeenCalledWith('hero.split')
    expect(execute).toHaveBeenCalledTimes(1)
    expect(execute).toHaveBeenCalledWith('insert-pattern', {
      sections: [{ type: 'hero', data: {} }, { type: 'cta', data: {} }],
    })
  })

  it('does not dispatch when the payload has no sections', async () => {
    const { execute } = seedEditorClient([])
    const store = usePatternStore()
    await store.load()
    const ok = await store.insert('empty.one')
    expect(ok).toBe(false)
    expect(execute).not.toHaveBeenCalled()
  })
})
