// Phase 26.1 — Metadata-driven, type-aware toolbar action model.
//
// A flat, declarative list of action descriptors resolved against the selected
// component's kind/type. The Vue toolbar renders whatever `resolveActions(ctx)`
// returns for the current selection — no per-type if/else chains in the template.
//
// Every `run` delegates to the SHARED editor / toolbar stores (which go through
// the command bus), so each action is exactly one undo entry. This module is pure
// (no Vue/Pinia imports): the toolbar passes a context object carrying the store
// delegates plus the selection facts each descriptor needs.

import type { ColumnLayoutPreset } from '../../editor/types'

/** A component "kind" for action resolution. Sections and containers are both
 * layout hosts; a column is a container child; everything else is a leaf block. */
export type ActionKind = 'section' | 'container' | 'column' | 'block'

/**
 * Everything a descriptor needs, supplied by the toolbar. The store delegates are
 * passed in (rather than imported) to keep this model pure + trivially testable.
 */
export interface ActionContext {
  /** Resolved action kind for the current selection. */
  kind: ActionKind
  /** The selected component id, or null. */
  componentId: string | null
  /** True when the selection can move one slot toward the start of its parent. */
  canMoveUp: boolean
  /** True when the selection can move one slot toward the end of its parent. */
  canMoveDown: boolean

  // ---- Delegates (all go through the command bus / toolbar store) ----------
  duplicate(): void
  copy(): void
  cut(): void
  delete(): void
  moveUp(): void
  moveDown(): void
  openOptions(): void
  /** Open the layout-preset popup for the current selection. */
  openLayoutPopup(): void
  /** Insert an empty column into the selected container. */
  addColumn(): void
  /** Insert a fresh container into the selected section. */
  addContainer(): void
  /** Insert a text element into the selected column. */
  addElement(): void
  /** Reverse the selected container's column order (one update-component-data). */
  reverseColumns(): void
  /** Commit a shallow data patch on the selection (align/justify/wrap/width/…). */
  patchData(data: Record<string, unknown>): void
  /** The selection's current data, so toggle/cycle actions can read the live value. */
  data: Record<string, unknown>
}

/** The next value after `current` in `values`, wrapping around — for cycle actions. */
function cycle(values: readonly string[], current: unknown): string {
  const index = values.indexOf(String(current))

  return values[(index + 1) % values.length]
}

export type ActionGroup = 'primary' | 'layout' | 'move' | 'more'

export interface ToolbarAction {
  id: string
  label: string
  icon: string
  group: ActionGroup
  /** Whether the action appears for the given context. */
  when: (ctx: ActionContext) => boolean
  /** Rendered but non-interactive (a seam, e.g. "Save as Pattern"). */
  disabled?: (ctx: ActionContext) => boolean
  /** Tooltip shown when disabled. */
  disabledHint?: string
  run: (ctx: ActionContext) => void | Promise<void>
}

const isSection = (ctx: ActionContext) => ctx.kind === 'section'
const isContainer = (ctx: ActionContext) => ctx.kind === 'container'
const isColumn = (ctx: ActionContext) => ctx.kind === 'column'

/**
 * The single ordered descriptor table for the whole toolbar. `resolveActions`
 * filters it by `when(ctx)`; the template groups the survivors. Shared actions
 * (Duplicate/Copy/Cut/Delete/Move/Open Options) reuse one descriptor across kinds
 * via a `when` that admits several kinds.
 */
export const TOOLBAR_ACTIONS: ToolbarAction[] = [
  // ---- Section ------------------------------------------------------------
  {
    id: 'add-container',
    label: 'Add Container',
    icon: '▦',
    group: 'layout',
    when: isSection,
    run: (ctx) => ctx.addContainer(),
  },
  {
    id: 'choose-layout',
    label: 'Choose Layout',
    icon: '▥',
    group: 'layout',
    when: isSection,
    run: (ctx) => ctx.openLayoutPopup(),
  },
  {
    id: 'save-as-pattern',
    label: 'Save as Pattern',
    icon: '❒',
    group: 'more',
    when: isSection,
    disabled: () => true,
    disabledHint: 'Coming soon',
    run: () => {
      /* seam — no-op until real pattern saving lands */
    },
  },

  // ---- Container ----------------------------------------------------------
  {
    id: 'change-layout',
    label: 'Change Layout',
    icon: '▥',
    group: 'layout',
    when: isContainer,
    run: (ctx) => ctx.openLayoutPopup(),
  },
  {
    id: 'add-column',
    label: 'Add Column',
    icon: '▤',
    group: 'layout',
    when: isContainer,
    run: (ctx) => ctx.addColumn(),
  },
  {
    id: 'reverse-columns',
    label: 'Reverse columns',
    icon: '⇄',
    group: 'more',
    when: isContainer,
    run: (ctx) => ctx.reverseColumns(),
  },
  {
    id: 'toggle-wrap',
    label: 'Toggle wrap',
    icon: '⤶',
    group: 'more',
    when: isContainer,
    run: (ctx) => ctx.patchData({ wrap: !ctx.data.wrap }),
  },
  {
    id: 'align-items',
    label: 'Align items',
    icon: '↕',
    group: 'more',
    when: isContainer,
    run: (ctx) => ctx.patchData({ align: cycle(['start', 'center', 'end', 'stretch'], ctx.data.align) }),
  },
  {
    id: 'justify-content',
    label: 'Justify content',
    icon: '↔',
    group: 'more',
    when: isContainer,
    run: (ctx) => ctx.patchData({ justify: cycle(['start', 'center', 'end', 'between', 'around'], ctx.data.justify) }),
  },

  // ---- Column -------------------------------------------------------------
  {
    id: 'column-width',
    label: 'Width',
    icon: '↔',
    group: 'layout',
    when: isColumn,
    run: (ctx) => ctx.openLayoutPopup(),
  },
  {
    id: 'move-left',
    label: 'Move left',
    icon: '←',
    group: 'move',
    when: (ctx) => isColumn(ctx) && ctx.canMoveUp,
    run: (ctx) => ctx.moveUp(),
  },
  {
    id: 'move-right',
    label: 'Move right',
    icon: '→',
    group: 'move',
    when: (ctx) => isColumn(ctx) && ctx.canMoveDown,
    run: (ctx) => ctx.moveDown(),
  },
  {
    id: 'add-element',
    label: 'Add Element',
    icon: '＋',
    group: 'layout',
    when: isColumn,
    run: (ctx) => ctx.addElement(),
  },
  {
    id: 'valign',
    label: 'Vertical align',
    icon: '↕',
    group: 'more',
    when: isColumn,
    run: (ctx) => ctx.patchData({ valign: cycle(['start', 'center', 'end'], ctx.data.valign) }),
  },
  {
    id: 'halign',
    label: 'Horizontal align',
    icon: '↔',
    group: 'more',
    when: isColumn,
    run: (ctx) => ctx.patchData({ halign: cycle(['stretch', 'start', 'center', 'end'], ctx.data.halign) }),
  },

  // ---- Shared (section + container + column) ------------------------------
  {
    id: 'duplicate',
    label: 'Duplicate',
    icon: '⧉',
    group: 'primary',
    when: () => true,
    run: (ctx) => ctx.duplicate(),
  },
  {
    id: 'copy',
    label: 'Copy',
    icon: '⧉',
    group: 'primary',
    // Copy/Cut are available on sections, containers, and ordinary leaf blocks.
    // The Column set intentionally omits them (spec §Column).
    when: (ctx) => !isColumn(ctx),
    run: (ctx) => ctx.copy(),
  },
  {
    id: 'cut',
    label: 'Cut',
    icon: '✂',
    group: 'primary',
    when: (ctx) => !isColumn(ctx),
    run: (ctx) => ctx.cut(),
  },
  {
    id: 'move-up',
    label: 'Move up',
    icon: '↑',
    group: 'move',
    when: (ctx) => isSection(ctx) && ctx.canMoveUp,
    run: (ctx) => ctx.moveUp(),
  },
  {
    id: 'move-down',
    label: 'Move down',
    icon: '↓',
    group: 'move',
    when: (ctx) => isSection(ctx) && ctx.canMoveDown,
    run: (ctx) => ctx.moveDown(),
  },
  {
    id: 'delete',
    label: 'Delete',
    icon: '✕',
    group: 'primary',
    when: () => true,
    run: (ctx) => ctx.delete(),
  },
  {
    id: 'open-options',
    // Labeled "Edit" in the UI (the established affordance); it opens the Options
    // sidebar tab for the selection.
    label: 'Edit',
    icon: '✎',
    group: 'primary',
    when: () => true,
    run: (ctx) => ctx.openOptions(),
  },
]

/** The descriptors that apply to the given context, in table order. */
export function resolveActions(ctx: ActionContext): ToolbarAction[] {
  return TOOLBAR_ACTIONS.filter((action) => action.when(ctx))
}

/** The action ids that apply to the given context (thin wrapper for tests/UI). */
export function resolveActionIds(ctx: ActionContext): string[] {
  return resolveActions(ctx).map((action) => action.id)
}

/**
 * Does applying `preset` to a container currently holding `columnCount` columns
 * drop any columns (fewer widths than columns)? Pure — the popup uses this plus a
 * "do the dropped columns hold content" check to decide whether to confirm.
 */
export function presetDropsColumns(preset: ColumnLayoutPreset, columnCount: number): boolean {
  return preset.widths.length < columnCount
}
