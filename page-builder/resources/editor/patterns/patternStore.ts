// Phase 25 — Pattern Library store (editor-local). Loads pattern manifests from
// the server (lazy — payloads only fetched on insert/preview), drives search /
// category / filter, and persists favorites + recently-used in localStorage. It
// never mutates BuilderState directly: insertion goes through the existing command
// flow (one `insert-pattern` command = one undo entry, new root selected).
import { defineStore } from 'pinia'
import { useEditorStore } from '../stores/editor'
import type {
  PatternCategory,
  PatternFilterMode,
  PatternManifest,
  PatternVariableValues,
} from './types'

const FAVORITES_KEY = 'pb.patterns.favorites'
const RECENT_KEY = 'pb.patterns.recent'
const RECENT_LIMIT = 20

function readIds(key: string): string[] {
  try {
    const raw = typeof localStorage !== 'undefined' ? localStorage.getItem(key) : null
    const parsed = raw ? JSON.parse(raw) : []
    return Array.isArray(parsed) ? parsed.filter((v): v is string => typeof v === 'string') : []
  } catch {
    return []
  }
}

function writeIds(key: string, ids: string[]): void {
  try {
    if (typeof localStorage !== 'undefined') localStorage.setItem(key, JSON.stringify(ids))
  } catch {
    // Storage unavailable (private mode / SSR) — favorites are best-effort.
  }
}

interface State {
  mode: 'patterns' | 'components'
  manifests: PatternManifest[]
  categories: PatternCategory[]
  loaded: boolean
  loading: boolean
  error: string | null
  query: string
  activeCategory: string | null
  filterMode: PatternFilterMode
  favorites: string[]
  recent: string[]
  inserting: string | null
  // Phase 25.1 — id of the pattern whose variables dialog is open (null = closed).
  variablesFor: string | null
}

export const usePatternStore = defineStore('pbPatterns', {
  state: (): State => ({
    mode: 'patterns',
    manifests: [],
    categories: [],
    loaded: false,
    loading: false,
    error: null,
    query: '',
    activeCategory: null,
    filterMode: 'all',
    favorites: readIds(FAVORITES_KEY),
    recent: readIds(RECENT_KEY),
    inserting: null,
    variablesFor: null,
  }),

  getters: {
    featured: (s): PatternManifest[] => s.manifests.filter((p) => p.featured),

    isFavorite: (s) => (id: string): boolean => s.favorites.includes(id),

    /** Whether a pattern declares insert-time variables (drives the dialog). */
    hasVariables: (s) => (id: string): boolean => {
      const pattern = s.manifests.find((p) => p.id === id)
      return !!pattern?.variables && pattern.variables.length > 0
    },

    /** The manifest whose variables dialog is currently open (if any). */
    variablesPattern(s): PatternManifest | undefined {
      return s.variablesFor ? s.manifests.find((p) => p.id === s.variablesFor) : undefined
    },

    /** Whether a pattern's dependencies are met (Phase 25.2). Absent = available. */
    isAvailable: (s) => (id: string): boolean => {
      const availability = s.manifests.find((p) => p.id === id)?.availability
      return availability ? availability.available : true
    },

    /** Human-readable reason a pattern is unavailable (empty when available). */
    unavailableReason: (s) => (id: string): string => {
      const availability = s.manifests.find((p) => p.id === id)?.availability
      if (!availability || availability.available) return ''
      const { plugins, components, capabilities } = availability.missing
      const parts: string[] = []
      if (plugins.length) parts.push(`plugin: ${plugins.join(', ')}`)
      if (components.length) parts.push(`component: ${components.join(', ')}`)
      if (capabilities.length) parts.push(`capability: ${capabilities.join(', ')}`)
      return parts.length ? `Requires ${parts.join('; ')}` : ''
    },

    /** Search + category + filter-mode applied, ordered for favorites/recent. */
    results(s): PatternManifest[] {
      const byId = new Map(s.manifests.map((p) => [p.id, p]))
      let list: PatternManifest[]

      if (s.filterMode === 'favorites') {
        list = s.favorites.map((id) => byId.get(id)).filter((p): p is PatternManifest => !!p)
      } else if (s.filterMode === 'recent') {
        list = s.recent.map((id) => byId.get(id)).filter((p): p is PatternManifest => !!p)
      } else {
        list = s.manifests.filter((p) => {
          if (s.filterMode === 'featured' && !p.featured) return false
          if (s.filterMode === 'free' && p.premium) return false
          if (s.filterMode === 'premium' && !p.premium) return false
          return true
        })
      }

      if (s.activeCategory) list = list.filter((p) => p.category === s.activeCategory)

      const q = s.query.trim().toLowerCase()
      if (q) {
        list = list.filter((p) =>
          [p.name, p.slug, p.description, p.category, ...p.tags, ...p.keywords]
            .join(' ')
            .toLowerCase()
            .includes(q),
        )
      }

      return list
    },
  },

  actions: {
    async load(force = false): Promise<void> {
      if ((this.loaded && !force) || this.loading) return
      const editor = useEditorStore()
      if (!editor.client) return
      this.loading = true
      this.error = null
      try {
        const data = await editor.client.getPatterns()
        this.manifests = data.patterns
        this.categories = data.categories
        this.loaded = true
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : 'Failed to load patterns.'
      } finally {
        this.loading = false
      }
    },

    setMode(mode: 'patterns' | 'components'): void {
      this.mode = mode
    },
    setQuery(query: string): void {
      this.query = query
    },
    setCategory(category: string | null): void {
      this.activeCategory = category
    },
    setFilterMode(mode: PatternFilterMode): void {
      this.filterMode = mode
    },

    find(id: string): PatternManifest | undefined {
      return this.manifests.find((p) => p.id === id)
    },

    /**
     * Entry point for inserting a pattern (Phase 25.1). When the pattern declares
     * variables, open the variables dialog first; otherwise insert immediately.
     */
    requestInsert(id: string): void {
      if (!this.isAvailable(id)) {
        this.error = this.unavailableReason(id) || 'Pattern is unavailable.'
        return
      }
      if (this.hasVariables(id)) {
        this.variablesFor = id
      } else {
        void this.insert(id)
      }
    },

    closeVariables(): void {
      this.variablesFor = null
    },

    /**
     * Insert a pattern via the existing command flow: fetch its (lazy) payload,
     * then dispatch ONE `insert-pattern` command (single undo entry; the server
     * re-keys ids and selects the new root). Records the pattern as recently used.
     *
     * When `values` is supplied (Phase 25.1) the payload is resolved server-side
     * against the pattern's variables first; without it the raw payload is used, so
     * patterns without variables keep their original one-step insert.
     */
    async insert(id: string, values?: PatternVariableValues): Promise<boolean> {
      const editor = useEditorStore()
      if (!editor.client || this.inserting) return false
      // Defense-in-depth: never insert a pattern whose dependencies are unmet.
      if (!this.isAvailable(id)) {
        this.error = this.unavailableReason(id) || 'Pattern is unavailable.'
        return false
      }
      this.inserting = id
      try {
        const detail = values !== undefined
          ? await editor.client.getResolvedPatternPayload(id, values)
          : await editor.client.getPatternPayload(id)
        const sections = detail.payload?.sections ?? []
        if (sections.length === 0) return false
        const ok = await editor.executeCommand('insert-pattern', { sections })
        if (ok) {
          this.pushRecent(id)
          this.variablesFor = null
        }
        return ok
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : 'Failed to insert pattern.'
        return false
      } finally {
        this.inserting = null
      }
    },

    favorite(id: string): void {
      if (!this.favorites.includes(id)) {
        this.favorites = [...this.favorites, id]
        writeIds(FAVORITES_KEY, this.favorites)
      }
    },
    unfavorite(id: string): void {
      this.favorites = this.favorites.filter((f) => f !== id)
      writeIds(FAVORITES_KEY, this.favorites)
    },
    toggleFavorite(id: string): void {
      this.favorites.includes(id) ? this.unfavorite(id) : this.favorite(id)
    },

    pushRecent(id: string): void {
      this.recent = [id, ...this.recent.filter((r) => r !== id)].slice(0, RECENT_LIMIT)
      writeIds(RECENT_KEY, this.recent)
    },
  },
})
