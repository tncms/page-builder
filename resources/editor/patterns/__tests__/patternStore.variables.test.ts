import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { usePatternStore } from '../patternStore'
import { useEditorStore } from '../../stores/editor'
import type { PatternManifest, PatternVariable } from '../types'

function variable(over: Partial<PatternVariable>): PatternVariable {
  return { id: 'x', type: 'text', label: 'X', default: '', required: false, options: [], ...over }
}

function manifest(over: Partial<PatternManifest>): PatternManifest {
  return {
    id: 'x', name: 'X', slug: 'x', category: 'hero', description: '', tags: [], version: '1.0.0',
    author: '', thumbnail: 'p.svg', thumbnail_url: '/t.svg', keywords: [], difficulty: 'beginner',
    featured: false, premium: false, previewWidth: 1280, previewHeight: 720, ...over,
  }
}

const PATTERNS: PatternManifest[] = [
  manifest({ id: 'plain.one', name: 'Plain' }),
  manifest({ id: 'hero.startup', name: 'Startup', variables: [variable({ id: 'title', default: 'Default' })] }),
]

function seed() {
  const editor = useEditorStore()
  const getPatternPayload = vi.fn(async () => ({
    manifest: manifest({ id: 'plain.one' }),
    payload: { version: 1, sections: [{ type: 'hero', data: {} }] },
  }))
  const getResolvedPatternPayload = vi.fn(async (_id: string, vars: Record<string, unknown>) => ({
    manifest: manifest({ id: 'hero.startup' }),
    payload: { version: 1, sections: [{ type: 'hero', data: { title: vars.title } }] },
  }))
  ;(editor as unknown as { client: unknown }).client = {
    getPatterns: vi.fn(async () => ({ patterns: PATTERNS, categories: [] })),
    getPatternPayload,
    getResolvedPatternPayload,
  }
  const execute = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
  return { editor, execute, getPatternPayload, getResolvedPatternPayload }
}

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
})

describe('patternStore — variables flow (Phase 25.1)', () => {
  it('hasVariables reflects the manifest', async () => {
    seed()
    const store = usePatternStore()
    await store.load()
    expect(store.hasVariables('hero.startup')).toBe(true)
    expect(store.hasVariables('plain.one')).toBe(false)
  })

  it('requestInsert opens the dialog for a pattern with variables (no immediate insert)', async () => {
    const { execute } = seed()
    const store = usePatternStore()
    await store.load()

    store.requestInsert('hero.startup')
    expect(store.variablesFor).toBe('hero.startup')
    expect(store.variablesPattern?.id).toBe('hero.startup')
    expect(execute).not.toHaveBeenCalled()
  })

  it('requestInsert inserts immediately for a pattern without variables', async () => {
    const { execute, getPatternPayload } = seed()
    const store = usePatternStore()
    await store.load()

    store.requestInsert('plain.one')
    await Promise.resolve()
    await Promise.resolve()

    expect(store.variablesFor).toBeNull()
    expect(getPatternPayload).toHaveBeenCalledWith('plain.one')
    expect(execute).toHaveBeenCalledWith('insert-pattern', { sections: [{ type: 'hero', data: {} }] })
  })

  it('insert(id, values) resolves server-side and dispatches ONE insert-pattern command', async () => {
    const { execute, getResolvedPatternPayload } = seed()
    const store = usePatternStore()
    await store.load()
    store.variablesFor = 'hero.startup'

    const ok = await store.insert('hero.startup', { title: 'Resolved' })

    expect(ok).toBe(true)
    expect(getResolvedPatternPayload).toHaveBeenCalledWith('hero.startup', { title: 'Resolved' })
    expect(execute).toHaveBeenCalledTimes(1)
    expect(execute).toHaveBeenCalledWith('insert-pattern', {
      sections: [{ type: 'hero', data: { title: 'Resolved' } }],
    })
    expect(store.variablesFor).toBeNull()
    expect(store.recent[0]).toBe('hero.startup')
  })

  it('closeVariables dismisses the dialog', async () => {
    seed()
    const store = usePatternStore()
    await store.load()
    store.variablesFor = 'hero.startup'
    store.closeVariables()
    expect(store.variablesFor).toBeNull()
  })
})
