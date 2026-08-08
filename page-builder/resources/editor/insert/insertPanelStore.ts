// Phase 26.37 — unified insert panel store (editor-local, router only).
//
// The single entry a user opens to insert anything. It presents the registered categories and, on
// selection, ROUTES to that category's existing experience via `provider.open()`. It performs no
// insertion itself: no resolve, no command, no BuilderState. `sync()` snapshots the registry so the
// panel reflects whatever the host wired.

import { defineStore } from 'pinia'
import { insertProviderRegistry } from './builtinInsertProviders'
import type { InsertProvider } from './InsertProvider'

interface State {
  isOpen: boolean
  providers: InsertProvider[]
  activeKey: string | null
  /** The last category routed to (for reactive host hooks / tests). */
  lastOpenedKey: string | null
}

export const useInsertPanelStore = defineStore('pbInsertPanel', {
  state: (): State => ({
    isOpen: false,
    providers: [],
    activeKey: null,
    lastOpenedKey: null,
  }),

  getters: {
    /** The provider for the active category, if any. */
    activeProvider(s): InsertProvider | undefined {
      return s.activeKey ? s.providers.find((p) => p.key === s.activeKey) : undefined
    },
    /** No categories are wired yet. */
    isEmpty: (s): boolean => s.providers.length === 0,
  },

  actions: {
    /** Snapshot the registry into reactive state (the panel's only data source). */
    sync(): void {
      this.providers = insertProviderRegistry.all()
    },

    /** Open the launcher (syncing categories first). */
    open(): void {
      this.sync()
      this.isOpen = true
    },

    close(): void {
      this.isOpen = false
    },

    toggle(): void {
      if (this.isOpen) this.close()
      else this.open()
    },

    /**
     * Route to a category: mark it active, record it, open its existing experience, and dismiss the
     * launcher. Unknown/unwired keys are a safe no-op. Never inserts, resolves, or dispatches.
     */
    select(key: string): boolean {
      const provider = this.providers.find((p) => p.key === key)
      if (!provider) return false
      this.activeKey = key
      this.lastOpenedKey = key
      provider.open()
      this.close()
      return true
    },
  },
})
