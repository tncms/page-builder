// Phase 26.23 — Template Apply store (confirmation flow, editor-local).
//
// Orchestrates the "confirm → apply" UX between the Picker selection and the runtime. It holds a
// pending selection, applies it on confirm via the runtime (`applyTemplateSelection`), and tracks
// applying / error / feedback. It performs NO layout work itself: the runtime owns resolution,
// the single `insert-pattern` command, and the one undo entry. `confirm()` is fault-isolated — a
// runtime failure (e.g. `template_not_found`, a resolution error) becomes an error state, never a
// thrown exception, and leaves BuilderState untouched (the runtime is atomic before any command).

import { defineStore } from 'pinia'
import { useEditorStore } from '../../stores/editor'
import type { TemplateApplyDeps } from '../runtime'
import type { TemplateSelection } from '../picker'
import { applyTemplateSelection } from './applyTemplateIntent'

interface State {
  pending: TemplateSelection | null
  applying: boolean
  error: string | null
  feedback: string | null
  lastAppliedId: string | null
}

export const useTemplateApplyStore = defineStore('pbTemplateApply', {
  state: (): State => ({
    pending: null,
    applying: false,
    error: null,
    feedback: null,
    lastAppliedId: null,
  }),

  getters: {
    /** A confirmation is open. */
    isOpen: (s): boolean => s.pending !== null,
  },

  actions: {
    /** Open the confirmation for a Picker selection (does not apply yet). */
    request(selection: TemplateSelection): void {
      this.pending = selection
      this.error = null
    },

    /** Dismiss the confirmation without applying. */
    cancel(): void {
      this.pending = null
      this.error = null
      this.applying = false
    },

    clearFeedback(): void {
      this.feedback = null
    },

    /** Apply the pending selection through the runtime (one command, one undo). Fault-isolated. */
    async confirm(deps: TemplateApplyDeps = {}): Promise<boolean> {
      if (!this.pending || this.applying) return false
      const selection = this.pending
      this.applying = true
      this.error = null
      try {
        const result = await applyTemplateSelection(selection, useEditorStore(), deps)
        if (!result.ok) {
          this.error = 'Template could not be applied.'
          return false
        }
        this.lastAppliedId = selection.templateId
        this.feedback = `Template "${selection.item.name}" applied.`
        this.pending = null
        return true
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : 'Template could not be applied.'
        return false
      } finally {
        this.applying = false
      }
    },
  },
})
