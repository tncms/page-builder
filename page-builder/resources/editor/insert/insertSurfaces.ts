// Phase 26.38 — host surfaces for Unified Insert integration.
//
// Two of the four insert experiences have no store-level "open" of their own: the component
// palette (blocks) lives in the left panel, and the Template Browser (templates) is a plain
// component. This tiny editor-local store gives the host something the Insert router can flip so
// those surfaces become visible — it holds UI intent ONLY. It never resolves, inserts, dispatches
// a command, or touches BuilderState. Patterns and Assets keep using their own picker stores.

import { defineStore } from 'pinia'

interface State {
  /** Bumped whenever "blocks" is routed — the left panel watches this to reveal the palette. */
  paletteToken: number
  /** Whether the Template Browser overlay is open (routed from "templates"). */
  templatesOpen: boolean
}

export const useInsertSurfacesStore = defineStore('pbInsertSurfaces', {
  state: (): State => ({
    paletteToken: 0,
    templatesOpen: false,
  }),

  actions: {
    /** Request the component palette be brought forward (blocks). UI intent only. */
    focusPalette(): void {
      this.paletteToken += 1
    },

    /** Open the Template Browser overlay (templates). */
    openTemplates(): void {
      this.templatesOpen = true
    },

    closeTemplates(): void {
      this.templatesOpen = false
    },
  },
})
