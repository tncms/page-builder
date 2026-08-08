import type { ComponentNode } from '../../types'
import type { useEditorStore } from '../../stores/editor'
import type { useNavigatorLocksStore } from '../../stores/navigatorLocks'
import type { InsertPlacement } from '../../utils/navigatorInsert'

/** Shape returned by `siblingPosition` — a node's place among its siblings. */
type SiblingPosition = { parentId: string | null; index: number; count: number }

/**
 * Navigator context-menu action registry (Phase 26.3 — pre-Increment-5 cleanup).
 *
 * The menu used to hardcode nine buttons with inline handlers and disabled
 * expressions. That made every new action a template + script edit and mixed
 * business logic into the view. This registry is the single source of truth for
 * WHAT actions exist and WHEN they show / are enabled; {@link NavigatorNode}
 * becomes a pure renderer that resolves this list for a row and draws it.
 *
 * Behaviour is intentionally byte-identical to the previous inline menu: same
 * labels, same order, same enabled/disabled rules, same store methods, same lock
 * authority and dirty guard (all enforced inside the store methods `execute`
 * calls). No new features are added.
 */

type EditorStore = ReturnType<typeof useEditorStore>
type NavigatorLocksStore = ReturnType<typeof useNavigatorLocksStore>

/** Everything an action needs to decide visibility/enablement and to run. */
export interface NavigatorActionContext {
  node: ComponentNode
  store: EditorStore
  locks: NavigatorLocksStore
  /** Editor-session lock state for this row (from the projected lock set). */
  isLocked: boolean
  /** Sibling position for reorder math, or null when the node isn't placed. */
  moveInfo: SiblingPosition | null
  canMoveUp: boolean
  canMoveDown: boolean
  /** A structural command is already in flight — matches the old buttons' guard. */
  commandLoading: boolean
  /** Begin inline rename on this row (owned by NavigatorNode; Increment 5). */
  beginRename: () => void
  /**
   * Per-placement insertion availability for this row (Increment 6), computed from
   * editor metadata + the central NestingPolicy: true only when a placement has at
   * least one valid candidate (and, for `inside`, the node can hold children).
   */
  insert: { inside: boolean; before: boolean; after: boolean }
  /** Open the reusable component picker for a placement (owned by NavigatorNode). */
  beginInsert: (placement: InsertPlacement) => void
}

export interface NavigatorAction {
  id: string
  label: string
  icon?: string
  shortcut?: string
  group?: string
  /** Renders with the danger styling (Delete). */
  danger?: boolean
  visible(ctx: NavigatorActionContext): boolean
  enabled(ctx: NavigatorActionContext): boolean
  /** Human reason shown when disabled (tooltip); omitted when enabled. */
  disabledReason?(ctx: NavigatorActionContext): string | undefined
  execute(ctx: NavigatorActionContext): void | Promise<void>
}

/** An action resolved against a concrete context, ready for the renderer. */
export interface ResolvedNavigatorAction {
  id: string
  label: string
  icon?: string
  shortcut?: string
  group?: string
  danger: boolean
  enabled: boolean
  disabledReason?: string
  execute: () => void | Promise<void>
}

const WORKING = 'Working…'
const LOCKED = 'Locked'

/**
 * The action list, in render order. Order and grouping mirror the previous menu
 * exactly: reorder → edit/clipboard → lock → danger.
 */
export const NAVIGATOR_ACTIONS: NavigatorAction[] = [
  {
    id: 'rename',
    label: 'Rename',
    group: 'edit',
    visible: () => true,
    enabled: (c) => !c.isLocked,
    disabledReason: (c) => (c.isLocked ? LOCKED : undefined),
    // UI-only: hands control to the row's inline input. The commit business logic
    // lives in the store (renameComponent), never in the view.
    execute: (c) => c.beginRename(),
  },
  // Structural insertion (Increment 6). Each is visible only when the placement has
  // at least one valid candidate (metadata + NestingPolicy, resolved upstream) —
  // "Only show valid actions" — and merely opens the reusable component picker; the
  // insertion itself runs through the store's single `insertComponent` command.
  {
    id: 'add-inside',
    label: 'Add Inside',
    group: 'insert',
    visible: (c) => c.insert.inside,
    enabled: (c) => !c.commandLoading && !c.isLocked,
    disabledReason: (c) => (c.commandLoading ? WORKING : c.isLocked ? LOCKED : undefined),
    execute: (c) => c.beginInsert('inside'),
  },
  {
    id: 'add-before',
    label: 'Add Before',
    group: 'insert',
    visible: (c) => c.insert.before,
    enabled: (c) => !c.commandLoading && !c.isLocked,
    disabledReason: (c) => (c.commandLoading ? WORKING : c.isLocked ? LOCKED : undefined),
    execute: (c) => c.beginInsert('before'),
  },
  {
    id: 'add-after',
    label: 'Add After',
    group: 'insert',
    visible: (c) => c.insert.after,
    enabled: (c) => !c.commandLoading && !c.isLocked,
    disabledReason: (c) => (c.commandLoading ? WORKING : c.isLocked ? LOCKED : undefined),
    execute: (c) => c.beginInsert('after'),
  },
  {
    id: 'move-up',
    label: 'Move Up',
    group: 'move',
    visible: () => true,
    enabled: (c) => !c.commandLoading && !c.isLocked && c.canMoveUp,
    disabledReason: (c) => (c.commandLoading ? WORKING : c.isLocked ? LOCKED : !c.canMoveUp ? 'Already first' : undefined),
    execute: (c) => {
      if (c.moveInfo) void c.store.moveComponent(c.node.id, c.moveInfo.parentId, c.moveInfo.index - 1)
    },
  },
  {
    id: 'move-down',
    label: 'Move Down',
    group: 'move',
    visible: () => true,
    enabled: (c) => !c.commandLoading && !c.isLocked && c.canMoveDown,
    disabledReason: (c) => (c.commandLoading ? WORKING : c.isLocked ? LOCKED : !c.canMoveDown ? 'Already last' : undefined),
    execute: (c) => {
      if (c.moveInfo) void c.store.moveComponent(c.node.id, c.moveInfo.parentId, c.moveInfo.index + 1)
    },
  },
  {
    id: 'duplicate',
    label: 'Duplicate',
    group: 'edit',
    visible: () => true,
    enabled: (c) => !c.commandLoading,
    disabledReason: (c) => (c.commandLoading ? WORKING : undefined),
    execute: (c) => void c.store.duplicateComponent(c.node.id),
  },
  {
    id: 'copy',
    label: 'Copy',
    group: 'edit',
    visible: () => true,
    enabled: (c) => !c.commandLoading,
    disabledReason: (c) => (c.commandLoading ? WORKING : undefined),
    execute: (c) => c.store.copyComponent(c.node.id),
  },
  {
    id: 'copy-id',
    label: 'Copy ID',
    group: 'edit',
    visible: () => true,
    enabled: () => true,
    execute: (c) => {
      // Clipboard may be unavailable (insecure context / permission denied) — ignore.
      void navigator.clipboard?.writeText(c.node.id).catch(() => {})
    },
  },
  {
    id: 'cut',
    label: 'Cut',
    group: 'edit',
    visible: () => true,
    enabled: (c) => !c.commandLoading && !c.isLocked,
    disabledReason: (c) => (c.commandLoading ? WORKING : c.isLocked ? LOCKED : undefined),
    execute: (c) => void c.store.cutComponent(c.node.id),
  },
  {
    id: 'paste-inside',
    label: 'Paste inside',
    group: 'edit',
    visible: () => true,
    enabled: (c) => !c.commandLoading && !c.isLocked,
    disabledReason: (c) => (c.commandLoading ? WORKING : c.isLocked ? LOCKED : undefined),
    execute: (c) => void c.store.pasteInto(c.node.id),
  },
  // Paste Before / Paste After are listed in the registry so relative paste has a
  // home when it lands, but there is NO store capability for it today. They are
  // feature-gated OFF (visible → false) so they never render and add no feature —
  // the menu stays byte-identical. Wire `visible`/`execute` in a future increment.
  {
    id: 'paste-before',
    label: 'Paste before',
    group: 'edit',
    visible: () => false,
    enabled: () => false,
    execute: () => {},
  },
  {
    id: 'paste-after',
    label: 'Paste after',
    group: 'edit',
    visible: () => false,
    enabled: () => false,
    execute: () => {},
  },
  // Lock and Unlock are two entries gated by lock state, so exactly one renders —
  // identical to the old single toggle button whose label flipped.
  {
    id: 'lock',
    label: 'Lock',
    group: 'lock',
    visible: (c) => !c.isLocked,
    enabled: () => true,
    execute: (c) => c.locks.toggle(c.store.owner, c.node.id),
  },
  {
    id: 'unlock',
    label: 'Unlock',
    group: 'lock',
    visible: (c) => c.isLocked,
    enabled: () => true,
    execute: (c) => c.locks.toggle(c.store.owner, c.node.id),
  },
  {
    id: 'delete',
    label: 'Delete',
    group: 'danger',
    danger: true,
    visible: () => true,
    enabled: (c) => !c.commandLoading && !c.isLocked,
    disabledReason: (c) => (c.commandLoading ? WORKING : c.isLocked ? LOCKED : undefined),
    execute: (c) => void c.store.deleteComponent(c.node.id),
  },
]

/** Resolve the registry against a row's context into a render-ready list. */
export function resolveNavigatorActions(
  ctx: NavigatorActionContext,
  actions: NavigatorAction[] = NAVIGATOR_ACTIONS,
): ResolvedNavigatorAction[] {
  const resolved: ResolvedNavigatorAction[] = []
  for (const action of actions) {
    if (!action.visible(ctx)) continue
    const enabled = action.enabled(ctx)
    resolved.push({
      id: action.id,
      label: action.label,
      icon: action.icon,
      shortcut: action.shortcut,
      group: action.group,
      danger: action.danger ?? false,
      enabled,
      disabledReason: enabled ? undefined : action.disabledReason?.(ctx),
      execute: () => action.execute(ctx),
    })
  }
  return resolved
}
