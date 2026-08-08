// Phase 18 — Inline edit orchestrator (shell, Pinia).
//
// Owns the lifecycle of an inline-edit session: opens on pb:inline-start, streams
// keystrokes to the iframe as live pb:patch-text (Phase 17), and commits through
// the same update-component-data command the Property Inspector uses — so an
// inline edit is just another BuilderState mutation (undo/redo, dirty, autosave).
// Cancelling restores the original text; committing lets the commit-time full
// render reconcile. It NEVER writes anything on its own; typing alone never
// commits (spec) — only blur / Enter / Ctrl+Enter / a flush do.

import { defineStore } from 'pinia'
import { useEditorStore } from '../../editor/stores/editor'
import { findComponentNode } from '../../editor/utils/layout'
import { InlineEditor } from './InlineEditor'
import type { InlineRect, InlineTarget } from './SelectionManager'

type Sender = (type: 'pb:patch-text' | 'pb:inline-end', payload: Record<string, unknown>) => void

// Module-level (non-reactive) bridge sender, like the geometry/flush singletons.
let sender: Sender | null = null

interface InlineState {
  editor: InlineEditor | null
  rect: InlineRect | null
  // Bumped whenever the editor value/target changes so Vue getters recompute.
  version: number
}

export const useInlineStore = defineStore('pbInline', {
  state: (): InlineState => ({
    editor: null,
    rect: null,
    version: 0,
  }),

  getters: {
    active: (state): boolean => state.editor !== null,
    value: (state): string => state.editor?.value ?? '',
    multiline: (state): boolean => state.editor?.target.multiline ?? false,
    typography: (state): Record<string, string> => state.editor?.target.typography ?? {},
    target: (state): InlineTarget | null => state.editor?.target ?? null,
  },

  actions: {
    bindSender(fn: Sender) {
      sender = fn
    },

    /** Begin editing a field. Any in-flight session is committed first. */
    open(target: InlineTarget) {
      if (this.editor) void this.commit()
      this.editor = new InlineEditor(target)
      this.rect = target.rect
      this.version += 1
    },

    /** A keystroke: stage the value and live-patch the iframe DOM. */
    setValue(value: string) {
      if (!this.editor) return
      this.editor.setValue(value)
      this.version += 1
      sender?.('pb:patch-text', {
        componentId: this.editor.target.componentId,
        selector: this.editor.target.selector,
        text: value,
      })
    },

    updateRect(rect: InlineRect) {
      if (this.editor) this.rect = rect
    },

    /** Commit the edit through update-component-data, then end the session. */
    async commit() {
      const editor = this.editor
      if (!editor) return

      // Detach immediately so a re-entrant open() can't clobber this session.
      this.editor = null
      this.rect = null
      this.version += 1

      if (editor.dirty) {
        const store = useEditorStore()
        const hit = findComponentNode(store.currentPayload, editor.target.componentId)
        const current =
          hit?.node?.data && typeof hit.node.data === 'object' ? (hit.node.data as Record<string, unknown>) : {}
        const data = editor.buildData(current, store.currentLocale)
        await store.updateComponentDataById(editor.target.componentId, data)
      }

      this.end(editor.target)
    },

    /** Discard the edit: restore the original text in the iframe, no command. */
    cancel() {
      const editor = this.editor
      if (!editor) return

      this.editor = null
      this.rect = null
      this.version += 1

      sender?.('pb:patch-text', {
        componentId: editor.target.componentId,
        selector: editor.target.selector,
        text: editor.original,
      })
      this.end(editor.target)
    },

    /** Commit any open session (used by selection switch / drag / save / publish). */
    async flush() {
      if (this.editor) await this.commit()
    },

    end(target: InlineTarget) {
      sender?.('pb:inline-end', { componentId: target.componentId, selector: target.selector })
    },
  },
})
