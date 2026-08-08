// Phase 26.1.1 — Layout hover preview (shell, Pinia).
//
// Mirrors BoxModelManager's live-patch-then-restore contract: hovering a preset
// re-proportions the selected container's EXISTING columns purely in the iframe
// DOM via Phase 17 pb:patch-style, and leaving/closing/applying restores each
// column's original width. It is a PREVIEW ONLY — it never commits, mutates
// BuilderState, marks dirty, autosaves, or creates an undo entry. The single
// mutation path stays ToolbarManager.applyLayoutPreset.
//
// Scope/limitation: the patch channel is intentionally non-structural, so a
// preset that changes the column COUNT previews only the overlapping columns
// (min(existing, widths)). Columns that would be added/removed are not shown.

import { defineStore } from 'pinia'
import { useEditorStore } from '../../editor/stores/editor'
import { findComponentNode } from '../../editor/utils/layout'

type Sender = (type: 'pb:patch-style', payload: Record<string, unknown>) => void

let sender: Sender | null = null

interface HoverColumn {
  id: string
  width: string
}

interface HoverState {
  // The container currently being previewed, and its columns' original widths.
  containerId: string | null
  originals: HoverColumn[]
  // The preset id currently shown (so re-hovering the same one is a no-op).
  activePresetId: string | null
}

/** Read a container's column children as { id, original-width } pairs. */
function readColumns(payload: unknown, containerId: string): HoverColumn[] {
  const hit = findComponentNode(payload as never, containerId)
  const data = hit?.node?.data as Record<string, unknown> | undefined
  const blocks = data?.blocks
  if (!Array.isArray(blocks)) return []
  const out: HoverColumn[] = []
  for (const block of blocks) {
    const b = block as Record<string, unknown>
    const id = typeof b.id === 'string' ? b.id : ''
    const colData = b.data as Record<string, unknown> | undefined
    const width = colData?.width == null ? '' : String(colData.width)
    if (id) out.push({ id, width })
  }
  return out
}

/**
 * A percent CSS value from a width string, or '' to clear the patch. Numeric
 * widths become `<n>%`; non-numeric widths (e.g. the default 'auto', or an empty
 * width) coerce to '' so restore clears the inline patch instead of writing an
 * invalid `auto%` the CSSOM would silently drop (leaving a stale preview). The
 * numeric coercion also means a metadata width can never inject arbitrary CSS.
 */
function cssWidth(width: string): string {
  const n = Number(width)

  return width.trim() !== '' && Number.isFinite(n) ? `${n}%` : ''
}

/** The pb:patch-style style map that sets a column's flex share from a width. */
function widthPatch(width: string): Record<string, string> {
  const value = cssWidth(width)

  return { flexBasis: value, maxWidth: value, width: value }
}

export const useLayoutHoverPreview = defineStore('pbLayoutHoverPreview', {
  state: (): HoverState => ({
    containerId: null,
    originals: [],
    activePresetId: null,
  }),

  getters: {
    previewing: (state): boolean => state.activePresetId !== null,
  },

  actions: {
    bindSender(fn: Sender) {
      sender = fn
    },

    /**
     * Preview `widths` on `containerId`'s existing columns. Captures each column's
     * original width the first time a container is previewed (so restore is exact),
     * then patches the overlapping columns to the preset's widths.
     */
    preview(containerId: string, widths: string[], presetId: string) {
      if (!containerId) return
      // Capture originals on first hover (or when switching containers).
      if (this.containerId !== containerId || this.originals.length === 0) {
        const editor = useEditorStore()
        this.containerId = containerId
        this.originals = readColumns(editor.currentPayload, containerId)
      }
      if (this.activePresetId === presetId) return
      this.activePresetId = presetId

      const count = Math.min(this.originals.length, widths.length)
      for (let i = 0; i < count; i++) {
        sender?.('pb:patch-style', {
          componentId: this.originals[i].id,
          selector: null,
          style: widthPatch(widths[i]),
        })
      }
    },

    /**
     * Restore every previewed column to its captured original width and clear the
     * session. A no-op when nothing is being previewed.
     */
    restore() {
      const originals = this.originals
      this.activePresetId = null
      this.containerId = null
      this.originals = []
      for (const column of originals) {
        // widthPatch clears (empty string) for non-numeric/absent widths, so an
        // 'auto' or unset column reverts to the stylesheet default rather than a
        // stale preview share.
        sender?.('pb:patch-style', {
          componentId: column.id,
          selector: null,
          style: widthPatch(column.width),
        })
      }
    },
  },
})
