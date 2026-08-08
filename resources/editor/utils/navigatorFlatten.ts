import type { ComponentNode } from '../types'

/**
 * Navigator v2 flattened visible model (Phase 26.3, Part 16/17).
 *
 * Keyboard navigation walks the tree as a flat, ordered list of exactly the rows
 * a user can see — a collapsed node's children are omitted — so ArrowUp/Down move
 * through what is on screen, never through hidden rows. Pure and deterministic:
 * the same tree + expansion predicate always yields the same list, so it is unit
 * tested without mounting anything.
 */
export interface FlatNode {
  id: string
  node: ComponentNode
  /** 1-based depth for `aria-level`. */
  level: number
  hasChildren: boolean
  expanded: boolean
}

export function flattenVisible(
  tree: ComponentNode[],
  isExpanded: (node: ComponentNode) => boolean,
  level = 1,
  acc: FlatNode[] = [],
): FlatNode[] {
  for (const node of tree) {
    const hasChildren = node.children.length > 0
    const expanded = hasChildren && isExpanded(node)
    acc.push({ id: node.id, node, level, hasChildren, expanded })
    if (expanded) {
      flattenVisible(node.children, isExpanded, level + 1, acc)
    }
  }
  return acc
}

/**
 * When the focused row disappears from the visible list (collapse, filter, or a
 * structural command like delete/cut), choose a STABLE replacement so keyboard
 * focus never lands on nothing. Deterministic chain (Part 7):
 * next visible sibling → previous visible sibling → parent → first visible → null.
 */
/**
 * Locate a node among its siblings (Phase 26.3, Part 4.1) so Move Up/Down can
 * compute the `move-component` index and disable at the ends. `parentId` is the
 * immediate parent id (null for a root section — reorders at root). Returns null
 * if the id is absent. Order mirrors the payload, which the mutator relies on.
 */
export function siblingPosition(
  tree: ComponentNode[],
  id: string,
): { parentId: string | null; index: number; count: number } | null {
  function walk(nodes: ComponentNode[], parentId: string | null): { parentId: string | null; index: number; count: number } | null {
    const index = nodes.findIndex((node) => node.id === id)
    if (index !== -1) {
      return { parentId, index, count: nodes.length }
    }
    for (const node of nodes) {
      const found = walk(node.children, node.id)
      if (found) {
        return found
      }
    }
    return null
  }

  return walk(tree, null)
}

export function resolveFocusFallback(previous: FlatNode[], next: FlatNode[], lostId: string): string | null {
  const nextIds = new Set(next.map((flat) => flat.id))
  const index = previous.findIndex((flat) => flat.id === lostId)

  if (index === -1) {
    return next[0]?.id ?? null
  }

  const parentId = previous[index].node.parentId

  for (let i = index + 1; i < previous.length; i++) {
    if (previous[i].node.parentId === parentId && nextIds.has(previous[i].id)) {
      return previous[i].id
    }
  }
  for (let i = index - 1; i >= 0; i--) {
    if (previous[i].node.parentId === parentId && nextIds.has(previous[i].id)) {
      return previous[i].id
    }
  }
  if (parentId && nextIds.has(parentId)) {
    return parentId
  }

  return next[0]?.id ?? null
}
