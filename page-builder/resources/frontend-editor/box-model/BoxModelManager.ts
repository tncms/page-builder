// Phase 19 — Box model orchestrator (shell, Pinia).
//
// Holds the measured metrics of the selected component and runs drag sessions:
// live-patching spacing via Phase 17 pb:patch-style while dragging, then
// committing to the flat `data.spacing` map through the same update-component-data
// command everything else uses. It never writes on its own; a drag that isn't
// released changes nothing permanent (cancel restores the pre-drag value).

import { defineStore } from 'pinia'
import { useEditorStore } from '../../editor/stores/editor'
import { findComponentNode } from '../../editor/utils/layout'
import { BoxModelSession } from './BoxModelSession'
import type { BoxMetrics, BoxType, Side } from './types'

type Sender = (type: 'pb:patch-style', payload: Record<string, unknown>) => void

let sender: Sender | null = null

// Built-in components the overlay offers spacing controls for.
export const SUPPORTED_TYPES = new Set([
  'hero',
  'text-section',
  'cta',
  'image-section',
  'columns',
  'heading',
  'text',
  'button',
  'image',
])

export function isSupportedType(type: string | undefined | null): boolean {
  return type != null && SUPPORTED_TYPES.has(type)
}

interface BoxState {
  metrics: BoxMetrics | null
  session: BoxModelSession | null
  dragValue: number | null
}

export const useBoxModelStore = defineStore('pbBoxModel', {
  state: (): BoxState => ({
    metrics: null,
    session: null,
    dragValue: null,
  }),

  getters: {
    dragging: (state): boolean => state.session !== null,
    // Only show for a supported component whose measured metrics we hold.
    visible(state): boolean {
      const editor = useEditorStore()
      const selected = editor.selectedComponentId
      return (
        selected != null &&
        state.metrics?.componentId === selected &&
        isSupportedType(editor.selectedComponent?.type)
      )
    },
  },

  actions: {
    bindSender(fn: Sender) {
      sender = fn
    },

    setMetrics(metrics: BoxMetrics) {
      // Ignore stale metrics for a component that is no longer selected.
      this.metrics = metrics
    },

    startDrag(boxType: BoxType, side: Side) {
      if (!this.metrics) return
      const start = this.metrics[boxType][side]
      this.session = new BoxModelSession(this.metrics.componentId, boxType, side, start)
      this.dragValue = start
    },

    updateDrag(dx: number, dy: number, snap = false) {
      const session = this.session
      if (!session) return
      const value = session.valueAt(dx, dy, snap)
      this.dragValue = value
      sender?.('pb:patch-style', {
        componentId: session.componentId,
        selector: null,
        style: session.stylePatch(value),
      })
    },

    /** Release: commit the new spacing to the component data. */
    async endDrag() {
      const session = this.session
      const value = this.dragValue
      this.session = null
      this.dragValue = null
      if (!session || value === null) return

      const store = useEditorStore()
      const hit = findComponentNode(store.currentPayload, session.componentId)
      const current =
        hit?.node?.data && typeof hit.node.data === 'object' ? (hit.node.data as Record<string, unknown>) : {}
      const spacing = session.spacingUpdate(current.spacing, value)
      await store.updateComponentDataById(session.componentId, { ...current, spacing })
    },

    /** Abort: restore the pre-drag value in the iframe and drop the session. */
    cancel() {
      const session = this.session
      this.session = null
      this.dragValue = null
      if (!session) return
      sender?.('pb:patch-style', {
        componentId: session.componentId,
        selector: null,
        style: session.stylePatch(session.startValue),
      })
    },
  },
})
