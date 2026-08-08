// Phase 26.1.1 — Layout-preset preferences (favorites + recents), persisted to
// localStorage under `pb:layout-presets`. This is UI preference state ONLY: it is
// NOT part of BuilderState, is never committed through a command, and never marks
// the document dirty or creates an undo entry. localStorage access is wrapped so
// a disabled/unavailable store (private mode, SSR) degrades to in-memory only.

import { defineStore } from 'pinia'

export const LAYOUT_PREFS_KEY = 'pb:layout-presets'
const RECENT_LIMIT = 10

interface PersistShape {
  favorites: string[]
  recent: string[]
}

interface PreferencesState {
  favorites: string[]
  recent: string[]
  loaded: boolean
}

function safeGet(): PersistShape | null {
  try {
    const raw = globalThis.localStorage?.getItem(LAYOUT_PREFS_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as Partial<PersistShape>
    return {
      favorites: Array.isArray(parsed.favorites) ? parsed.favorites.filter((v) => typeof v === 'string') : [],
      recent: Array.isArray(parsed.recent) ? parsed.recent.filter((v) => typeof v === 'string') : [],
    }
  } catch {
    return null
  }
}

function safeSet(value: PersistShape): void {
  try {
    globalThis.localStorage?.setItem(LAYOUT_PREFS_KEY, JSON.stringify(value))
  } catch {
    // Unavailable / quota exceeded — keep in-memory state, drop persistence.
  }
}

export const useLayoutPreferences = defineStore('pbLayoutPreferences', {
  state: (): PreferencesState => ({
    favorites: [],
    recent: [],
    loaded: false,
  }),

  getters: {
    isFavorite: (state) => (id: string): boolean => state.favorites.includes(id),
  },

  actions: {
    /** Hydrate from localStorage once. Safe to call repeatedly. */
    load() {
      if (this.loaded) return
      const stored = safeGet()
      if (stored) {
        this.favorites = stored.favorites
        this.recent = stored.recent.slice(0, RECENT_LIMIT)
      }
      this.loaded = true
    },

    persist() {
      safeSet({ favorites: [...this.favorites], recent: [...this.recent] })
    },

    /** Star / unstar a preset. */
    toggleFavorite(id: string) {
      const i = this.favorites.indexOf(id)
      if (i === -1) this.favorites = [...this.favorites, id]
      else this.favorites = this.favorites.filter((v) => v !== id)
      this.persist()
    },

    /** Record a preset as applied: move-to-front, de-duplicate, cap at 10. */
    addRecent(id: string) {
      this.recent = [id, ...this.recent.filter((v) => v !== id)].slice(0, RECENT_LIMIT)
      this.persist()
    },
  },
})
