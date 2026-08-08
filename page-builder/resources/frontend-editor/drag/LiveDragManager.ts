// Phase 22.1 — Live Builder drag orchestrator (shell, Pinia).
//
// The Live Builder preview lives in an iframe, so the structure editor's
// DragManager/GeometryManager (bound to the visual canvas) can't drive it. This
// store owns the shell half of a cross-iframe drag: it starts a session (from
// the toolbar handle or the Elements palette), tracks the ghost + the iframe's
// reported resolution, and commits EXACTLY ONE command on drop through the
// existing command bus. It never mutates the payload mid-drag.

import { defineStore } from 'pinia'
import { useEditorStore } from '../../editor/stores/editor'
import {
  buildNodeTree,
  componentLocation,
  findComponentById,
  isContainerId,
} from '../../editor/utils/layout'
import { createComponentPayload } from '../../editor/utils/palette'
import { describeDropIntent, type DropIntent } from '../../editor/utils/dropIntent'
import { moveDropIndex, insertDropIndex } from './dropIndex'
import { pbDebugEnabled } from '../debug'
import type { DefinitionMeta } from '../../editor/types'
import type { LiveDragSubject, LiveDropResolution, LiveGhost } from './types'
import { resolveIconSvg } from '../shell/components/iconLibrary'
import { useBuilderToastStore } from '../shell/builderToastStore'
import { SECTION_AT_PAGE_LEVEL_TOAST } from '../shell/builderGuidance'

/**
 * A Section drop that landed at page level *while the pointer was aimed inside
 * content* (the deepest hovered marker was not the root section it snapped next
 * to). This is exactly the "I tried to drop a Section into a Column" case — the
 * outcome is unchanged (Sections still snap to page level), we only surface a
 * non-blocking explanation.
 */
function sectionSnappedFromInside(subject: LiveDragSubject, resolution: LiveDropResolution): boolean {
  return (
    subject.kind === 'section' &&
    !!resolution.targetId &&
    !!resolution.referenceId &&
    resolution.targetId !== resolution.referenceId
  )
}

// SHELL_TO_IFRAME drag messages the store asks the bridge to send.
type Sender = (type: string, payload?: unknown) => void

let sender: Sender | null = null

// Phase 26.3.9 / 26.3.11 — dev-only "drop-debug" runtime trace of the committed
// drop. Proves, at the moment of commit, the full contract: the dragged SOURCE
// (type + kind), the TARGET under the pointer, the RESOLUTION (action + parentId),
// that validation was accepted, and the COMMAND that ran (name + target_parent_id)
// with its result + newNodeId. Gated to DEV so it never runs in production
// (mirrors the editor store's palette-drop debug).
function traceDrop(
  subject: LiveDragSubject,
  intent: DropIntent,
  resolution: LiveDropResolution,
  committed: boolean,
  newNodeId: string | null,
): void {
  if (!pbDebugEnabled()) return
  const command = intent.mode === 'palette' ? 'insert-component' : 'move-component'
  // eslint-disable-next-line no-console
  console.debug('[page-builder] drop-debug', {
    source: { mode: intent.mode, type: subject.type, kind: subject.kind },
    target: { id: resolution.targetId, type: intent.targetType },
    resolution: { action: resolution.edge, parentId: resolution.parentId },
    validation: 'accepted',
    command: { name: command, target_parent_id: resolution.parentId },
    result: committed ? 'success' : 'noop',
    newNodeId,
  })
}

interface LiveDragState {
  active: boolean
  subject: LiveDragSubject | null
  ghost: LiveGhost | null
  resolution: LiveDropResolution | null
}

export const useLiveDragStore = defineStore('pbLiveDrag', {
  state: (): LiveDragState => ({
    active: false,
    subject: null,
    ghost: null,
    resolution: null,
  }),

  getters: {
    dragging: (state): boolean => state.active,
    valid: (state): boolean => state.resolution?.valid ?? false,
    markerRect: (state) => state.resolution?.markerRect ?? null,
    markerEdge: (state) => state.resolution?.edge ?? null,
    ghostLabel: (state): string => state.subject?.label ?? '',
    ghostType: (state): string => state.subject?.type ?? '',
    ghostIcon: (state): string | null => state.subject?.icon ?? null,
  },

  actions: {
    bindSender(fn: Sender) {
      sender = fn
    },

    /** Every section + container block id (a DOM marker can't say container). */
    containerIds(): string[] {
      const editor = useEditorStore()
      const out: string[] = []
      const walk = (nodes: ReturnType<typeof buildNodeTree>): void => {
        for (const node of nodes) {
          if (node.kind === 'section' || isContainerId(editor.currentPayload, node.id)) {
            out.push(node.id)
          }
          walk(node.children)
        }
      }
      walk(buildNodeTree(editor.currentPayload))
      return out
    },

    /** Begin dragging an existing component (from the toolbar handle). */
    startFromComponent(id: string, event: PointerEvent) {
      const editor = useEditorStore()
      const loc = componentLocation(editor.currentPayload, id)
      const meta = findComponentById(editor.currentPayload, id)
      if (!loc || !meta) return

      const def = editor.definitionOf(meta.kind, meta.type)
      this.begin(
        {
          id,
          kind: meta.kind,
          type: meta.type,
          label: (def?.label as string) || (def?.name as string) || meta.type,
          icon: resolveIconSvg(def?.icon as string | undefined, def?.category as string | undefined),
          parentId: loc.parentId,
          index: loc.index,
        },
        event,
      )
    },

    /** Begin dragging a brand-new component from the Elements palette. */
    startFromPalette(definition: DefinitionMeta, kind: 'section' | 'block', event: PointerEvent) {
      this.begin(
        {
          id: `palette:${definition.type}:${Date.now()}`,
          kind,
          type: definition.type,
          label: (definition.label as string) || (definition.name as string) || definition.type,
          icon: resolveIconSvg(definition.icon as string | undefined, definition.category as string | undefined),
          parentId: null,
          index: 0,
          source: 'palette',
          component: createComponentPayload(definition, kind) as unknown as Record<string, unknown>,
        },
        event,
      )
    },

    begin(subject: LiveDragSubject, event: PointerEvent) {
      this.active = true
      this.subject = subject
      // pointerdown is a shell event: clientX/Y are already shell-window coords.
      this.ghost = { x: event.clientX, y: event.clientY }
      this.resolution = null
      sender?.('pb:drag-start', {
        subject: {
          id: subject.id,
          kind: subject.kind,
          type: subject.type,
          parentId: subject.parentId,
          index: subject.index,
          source: subject.source,
        },
        containerIds: this.containerIds(),
      })
      // Phase 26.3.12 — DEV: prove the real-UI drag payload (componentType/kind/
      // source) at the moment the session starts. Stripped from production.
      if (pbDebugEnabled()) {
        // eslint-disable-next-line no-console
        console.debug('[PB-LIVE-DRAG-START]', {
          componentType: subject.type,
          kind: subject.kind,
          source: subject.source ?? 'move',
        })
      }
    },

    /** Move the ghost (shell-window coords). */
    updatePointer(x: number, y: number) {
      if (!this.active) return
      this.ghost = { x, y }
    },

    /** Store the iframe's resolved drop + echo it back so the iframe highlights. */
    setResolution(resolution: LiveDropResolution) {
      if (!this.active) return
      this.resolution = resolution
      sender?.('pb:drag-highlight-target', resolution)
    },

    /** Pointer left the iframe: keep the ghost, drop the target/marker. */
    clearResolution() {
      this.resolution = null
      sender?.('pb:drag-highlight-target', null)
    },

    /** Abort the drag (Escape / release over shell chrome / iframe cancel). */
    cancel() {
      if (!this.active) return
      this.reset()
      sender?.('pb:drag-cancel', {})
    },

    /**
     * Commit the terminal drop: exactly one move/insert command, or nothing when
     * the drop is invalid. Preserves selection on a move; the structural change
     * triggers a full preview refresh (no reconcile directive).
     */
    async drop(resolution: LiveDropResolution | null) {
      const editor = useEditorStore()
      const subject = this.subject
      this.reset()
      sender?.('pb:drag-end', {})

      if (!subject || !resolution || !resolution.valid) return

      // Single pre-commit gate (Increment 7): the live/iframe path now shares the
      // editor's DropIntent guard — lock + nesting + move-into-self (sync) + the
      // inspector dirty-guard — exactly like the visual-canvas and outline drops.
      const intent = describeDropIntent(subject, resolution, (id) => editor.nodeTypeOf(id))
      if (editor.dropBlocked(intent)) return
      if (editor.inspectorDirty && !(await editor.guardInspector())) return

      if (subject.source === 'palette') {
        if (!subject.component) return
        const index = insertDropIndex(editor.currentPayload, resolution)
        const changed = await editor.executeCommand('insert-component', {
          component: subject.component,
          target_parent_id: resolution.parentId,
          index,
        })
        // insert-component selects the new node server-side (Phase 26.3.8), so
        // selectedComponentId is the freshly inserted id.
        if (changed && sectionSnappedFromInside(subject, resolution)) {
          useBuilderToastStore().notifySectionAtPageLevel(SECTION_AT_PAGE_LEVEL_TOAST)
        }
        traceDrop(subject, intent, resolution, changed, changed ? editor.selectedComponentId : null)
        return
      }

      const index = moveDropIndex(editor.currentPayload, subject, resolution)
      if (index === null) return
      const moved = await editor.executeCommand('move-component', {
        component_id: subject.id,
        target_parent_id: resolution.parentId,
        index,
      })
      if (moved) editor.selectComponent(subject.id)
      if (moved && sectionSnappedFromInside(subject, resolution)) {
        useBuilderToastStore().notifySectionAtPageLevel(SECTION_AT_PAGE_LEVEL_TOAST)
      }
      traceDrop(subject, intent, resolution, moved, moved ? subject.id : null)
    },

    reset() {
      this.active = false
      this.subject = null
      this.ghost = null
      this.resolution = null
    },
  },
})
