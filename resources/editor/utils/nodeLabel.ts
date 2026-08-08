import type { ComponentNode } from '../types'

/**
 * Navigator display-label resolution (Phase 26.3, Increment 5 — Inline Rename).
 *
 * A node has no dedicated label field. Its Navigator label is normally the
 * component DEFINITION label (the type, e.g. "Heading"). Inline rename lets a
 * user give a single node a custom name; we store that as an inert `_label` meta
 * key inside the component's existing `data` map — reusing the `update-component-data`
 * command (undo/redo for free) and inventing NO new storage, command, or schema
 * field. The public renderer only reads the keys its block template declares, so
 * `_label` never affects rendered output.
 */

/** Max characters kept for a custom node label — protects against pathological input. */
export const NAV_LABEL_MAX = 120

/** The meta key a custom label lives under inside a component's `data`. */
export const NODE_LABEL_KEY = '_label'

/** The custom label for a node, or null when it has none (blank counts as none). */
export function customNodeLabel(node: ComponentNode): string | null {
  const raw = (node.data as Record<string, unknown> | undefined)?.[NODE_LABEL_KEY]
  return typeof raw === 'string' && raw.trim() !== '' ? raw.trim() : null
}

/** The definition (type) label used when a node has no custom name. */
export function fallbackNodeLabel(def: { label?: unknown; name?: unknown } | null | undefined, type: string): string {
  const raw = (def?.label as string) || (def?.name as string) || type
  return raw.charAt(0).toUpperCase() + raw.slice(1)
}

/** The label shown in the Navigator: custom name if set, else the type label. */
export function displayNodeLabel(node: ComponentNode, def: { label?: unknown; name?: unknown } | null | undefined): string {
  return customNodeLabel(node) ?? fallbackNodeLabel(def, node.type)
}
