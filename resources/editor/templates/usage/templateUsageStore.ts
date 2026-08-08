// Phase 26.32 — template usage store (editor-local wiring).
//
// Wires the framework-free `TemplateUsageManager` to the real editor stores: it reads the default
// adoption discovery provider and hands off to the existing Phase 26.23 apply store's `request`,
// which opens the confirm → apply flow. The usage store itself applies NOTHING — no runtime, no
// command, no BuilderState. It records only a thin last-intent outcome for UX feedback.

import { defineStore } from 'pinia'
import { useTemplateApplyStore } from '../apply'
import type { TemplateSelection } from '../picker'
import { TemplateUsageManager } from './TemplateUsageManager'
import type { UsageIntentReason } from './templateUsageIntent'

interface State {
  lastUsedId: string | null
  reason: UsageIntentReason | null
  feedback: string | null
}

export const useTemplateUsageStore = defineStore('pbTemplateUsage', {
  state: (): State => ({
    lastUsedId: null,
    reason: null,
    feedback: null,
  }),

  actions: {
    /**
     * Begin using an adopted template by its library-wide id. On success this opens the existing
     * apply confirmation (the user still confirms; the runtime still owns execution). Returns
     * whether the hand-off started; failures set `reason` and never throw.
     */
    use(id: string): boolean {
      const manager = new TemplateUsageManager(undefined, (selection) =>
        useTemplateApplyStore().request(selection),
      )
      const result = manager.use(id)
      if (!result.ok) {
        this.reason = result.reason ?? null
        this.feedback = null
        return false
      }
      this.lastUsedId = id
      this.reason = null
      this.feedback = `Ready to apply "${result.selection?.item.name}".`
      return true
    },

    /**
     * Use a template the caller already holds as a selection (e.g. the library workspace). Opens the
     * existing apply confirmation via the usage connector; applies nothing itself.
     */
    useSelection(selection: TemplateSelection): void {
      const manager = new TemplateUsageManager(undefined, (s) =>
        useTemplateApplyStore().request(s),
      )
      manager.useSelection(selection)
      this.lastUsedId = selection.templateId
      this.reason = null
      this.feedback = `Ready to apply "${selection.item.name}".`
    },

    clear(): void {
      this.reason = null
      this.feedback = null
    },
  },
})
