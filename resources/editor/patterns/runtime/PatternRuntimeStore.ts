// Phase 26.15 — runtime-only Pattern UX state.
//
// "Recently Created" and "Recently Used" are IN-MEMORY ONLY — no persistence, no
// storage, no analytics. They reset on reload (a fresh Pinia store). Ids are resolved to
// live definitions through the provider on read, so renamed patterns show their new name
// and deleted ones drop out automatically. Also hosts the Ctrl/Cmd+Shift+P shortcut that
// opens the EXISTING picker (never a second picker).

import { defineStore } from 'pinia'
import { patternProvider } from '../provider'
import { usePatternPickerStore } from '../picker/patternPickerStore'
import type { PatternDefinition } from '../library'

/** How many ids each "recent" list keeps. */
export const RECENT_LIMIT = 10

interface State {
  recentlyCreatedIds: string[]
  recentlyUsedIds: string[]
}

/** Most-recent-first, de-duplicated, capped. */
function pushRecent(ids: string[], id: string): string[] {
  return [id, ...ids.filter((existing) => existing !== id)].slice(0, RECENT_LIMIT)
}

/** Resolve ids to live definitions, dropping any that no longer exist. */
function resolve(ids: string[]): PatternDefinition[] {
  return ids.map((id) => patternProvider.get(id)).filter((p): p is PatternDefinition => !!p)
}

export const usePatternRuntimeStore = defineStore('pbPatternRuntime', {
  state: (): State => ({ recentlyCreatedIds: [], recentlyUsedIds: [] }),

  getters: {
    recentlyCreated: (s): PatternDefinition[] => resolve(s.recentlyCreatedIds),
    recentlyUsed: (s): PatternDefinition[] => resolve(s.recentlyUsedIds),
  },

  actions: {
    /** Record a newly created/duplicated pattern. */
    trackCreated(id: string): void {
      this.recentlyCreatedIds = pushRecent(this.recentlyCreatedIds, id)
    },
    /** Record a pattern that was inserted. */
    trackUsed(id: string): void {
      this.recentlyUsedIds = pushRecent(this.recentlyUsedIds, id)
    },
    /** Clear both lists (e.g. on explicit reset). */
    reset(): void {
      this.recentlyCreatedIds = []
      this.recentlyUsedIds = []
    },

    /**
     * Open the picker on Ctrl/Cmd+Shift+P. Returns whether the event was handled, so a
     * host can decide whether to stop propagation.
     */
    handleShortcut(event: KeyboardEvent): boolean {
      const combo = (event.ctrlKey || event.metaKey) && event.shiftKey && (event.key === 'P' || event.key === 'p')
      if (!combo) return false
      event.preventDefault()
      usePatternPickerStore().openPicker()
      return true
    },
  },
})

/**
 * Attach the pattern keyboard shortcuts to a target (default `window`) and return a
 * disposer. Wire this once from the editor host; it opens the existing picker.
 */
export function registerPatternShortcuts(target: Pick<Window, 'addEventListener' | 'removeEventListener'> = window): () => void {
  const handler = (event: Event): void => {
    usePatternRuntimeStore().handleShortcut(event as KeyboardEvent)
  }
  target.addEventListener('keydown', handler)
  return () => target.removeEventListener('keydown', handler)
}
