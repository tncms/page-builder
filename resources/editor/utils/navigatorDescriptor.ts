import type { ComponentNode } from '../types'

/**
 * Navigator v2 node descriptor (Phase 26.3, Part 4).
 *
 * A pure, DETERMINISTIC projection of a {@link ComponentNode} plus the current
 * editor-session context into the flat metadata the Navigator v2 rows render.
 * Nothing here reads global state, the clock, or randomness — the same node +
 * context always yields the same descriptor — so it is trivially unit-testable
 * and safe to call inside a Vue computed on every render.
 *
 * It INVENTS no facts (Part 2): `childCount` and `canContainChildren` come from
 * the node's own shape (a node can contain children iff it is a section or its
 * data carries a `blocks` array — the same rule the layout/nesting helpers use),
 * `isHidden` mirrors the existing `disabled` flag, and `isDirty`/`isLocked`/
 * `isSelected` are read from the session context. Lock is an editor-session
 * concept (Part 13): it lives in `context.lockedIds`, never in the payload.
 */
export interface NavigatorDescriptorContext {
  /** Currently selected component id, or null. */
  selectedId: string | null
  /** Whether the inspector has un-applied draft edits for the selection. */
  inspectorDirty: boolean
  /** Editor-session locked ids (not persisted, not a payload field). */
  lockedIds: ReadonlySet<string>
}

export interface NavigatorDescriptor {
  id: string
  kind: string
  type: string
  /** Number of direct children. */
  childCount: number
  /** Whether this node is allowed to hold children (even when currently empty). */
  canContainChildren: boolean
  /** Normalized column width label (e.g. "50%", "auto") for columns; null otherwise. */
  columnWidth: string | null
  isColumn: boolean
  isSelected: boolean
  /** True only for the selected node while the inspector holds un-applied edits. */
  isDirty: boolean
  isLocked: boolean
  /** Mirrors the node's existing disabled/hidden flag. */
  isHidden: boolean
}

/**
 * Alias → percentage map matching the legacy column-width vocabulary the
 * Navigator has always displayed (kept byte-identical so the label never shifts).
 */
const COLUMN_WIDTH_MAP: Record<string, string> = {
  '100': '100%', '75': '75%', '66': '66.6667%', '50': '50%', '33': '33.3333%', '25': '25%',
  full: '100%', half: '50%', third: '33.3333%', quarter: '25%',
}

/** Normalize a raw column width (alias, number, or "auto") to its display label. */
export function normalizeColumnWidth(raw: unknown): string {
  const key = String(raw ?? 'auto')

  return COLUMN_WIDTH_MAP[key] ?? (key === 'auto' ? 'auto' : key)
}

/** Whether a node may hold children — section, or a block whose data has a `blocks` array. */
export function canContainChildren(node: ComponentNode): boolean {
  return node.kind === 'section' || Array.isArray((node.data as { blocks?: unknown } | undefined)?.blocks)
}

/** Project a component node + session context into the flat Navigator v2 descriptor. */
export function describeNode(node: ComponentNode, context: NavigatorDescriptorContext): NavigatorDescriptor {
  const isColumn = node.type === 'column'
  const isSelected = node.id === context.selectedId

  return {
    id: node.id,
    kind: node.kind,
    type: node.type,
    childCount: node.children.length,
    canContainChildren: canContainChildren(node),
    columnWidth: isColumn ? normalizeColumnWidth((node.data as { width?: unknown } | undefined)?.width) : null,
    isColumn,
    isSelected,
    isDirty: isSelected && context.inspectorDirty,
    isLocked: context.lockedIds.has(node.id),
    isHidden: node.disabled === true,
  }
}
