// Phase 26.3.18 — Builder toast store.
//
// A tiny, non-blocking notification queue for intent guidance (e.g. "Sections are
// placed at page level"). Deliberately minimal: it never interrupts editing, never
// blocks a command, and auto-dismisses. Rendered by BuilderToast.vue with an ARIA
// live region so screen readers announce it.

import { defineStore } from 'pinia'

export interface BuilderToast {
  id: number
  message: string
  /** Optional single action (e.g. the element-twin smart suggestion). */
  action?: { label: string; run: () => void }
}

const DEFAULT_TTL = 6000

let seq = 0

export const useBuilderToastStore = defineStore('pb-builder-toast', {
  state: () => ({
    toasts: [] as BuilderToast[],
    timers: {} as Record<number, ReturnType<typeof setTimeout>>,
  }),
  actions: {
    /** Show a toast; returns its id. Auto-dismisses after `ttl` ms (0 = sticky). */
    show(message: string, options: { action?: BuilderToast['action']; ttl?: number } = {}): number {
      seq += 1
      const id = seq
      this.toasts.push({ id, message, action: options.action })
      const ttl = options.ttl ?? DEFAULT_TTL
      if (ttl > 0) {
        this.timers[id] = setTimeout(() => this.dismiss(id), ttl)
      }
      return id
    },
    /** Convenience: the section-at-page-level guidance, optionally with a twin action. */
    notifySectionAtPageLevel(message: string, action?: BuilderToast['action']): number {
      return this.show(message, { action })
    },
    dismiss(id: number): void {
      const timer = this.timers[id]
      if (timer) {
        clearTimeout(timer)
        delete this.timers[id]
      }
      this.toasts = this.toasts.filter((t) => t.id !== id)
    },
    clear(): void {
      for (const id of Object.keys(this.timers)) clearTimeout(this.timers[Number(id)])
      this.timers = {}
      this.toasts = []
    },
  },
})
