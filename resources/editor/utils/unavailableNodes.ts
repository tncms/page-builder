// PB-XREG-4-R1 — editor-side view of "unavailable" components (a namespaced `provider:name`
// type whose provider is not registered right now). Such a node is an opaque frozen subtree:
// it is kept exactly as saved, and the editor offers no action that would change it, its
// descendants, its parent or its order among siblings. The server enforces the same rule on
// every save; these helpers only keep the UI from offering a change the server will refuse.
// A BARE unknown type (no namespace) is not "unavailable" — it stays an invalid layout.

import type { BlockNode, ComponentNode, LayoutPayload, SectionNode } from '../types'
import type { DropIntent } from './dropIntent'
import { buildNodeTree, componentPath } from './layout'

/** Same grammar and bounds as the server's ComponentIdentifier (`provider:local`). */
const SEGMENT = /^[a-z0-9]+(-[a-z0-9]+)*$/
const MAX_SEGMENT = 64
const MAX_TOTAL = 129

export type KnownType = (kind: string, type: string) => boolean

export function isExternalComponentType(type: unknown): boolean {
  if (typeof type !== 'string') return false
  const value = type.trim()
  if (value === '' || value.length > MAX_TOTAL) return false
  const parts = value.split(':')
  if (parts.length !== 2) return false
  return parts.every((part) => part.length <= MAX_SEGMENT && SEGMENT.test(part))
}

/** A namespaced type with no registered definition right now. */
export function isUnavailableType(kind: string, type: unknown, isKnown: KnownType): boolean {
  return isExternalComponentType(type) && !isKnown(kind, String(type))
}

/** The node itself, or any of its ancestors, is unavailable. */
export function isFrozenNode(payload: LayoutPayload | null | undefined, id: string | null | undefined, isKnown: KnownType): boolean {
  if (!id) return false
  return componentPath(payload, id).some((item) => isUnavailableType(item.kind, item.type, isKnown))
}

/** Ids of the top-most unavailable nodes (what blocks publishing). */
export function unavailableRootIds(payload: LayoutPayload | null | undefined, isKnown: KnownType): string[] {
  const out: string[] = []
  const walk = (nodes: ComponentNode[]): void => {
    for (const node of nodes) {
      if (isUnavailableType(node.kind, node.type, isKnown)) out.push(node.id)
      else walk(node.children)
    }
  }
  walk(buildNodeTree(payload))
  return out
}

/** A copied/cut node (raw) that contains an unavailable type anywhere in its subtree. */
export function containsUnavailable(node: SectionNode | BlockNode, kind: string, isKnown: KnownType): boolean {
  if (isUnavailableType(kind, node.type, isKnown)) return true
  const children = kind === 'section'
    ? (node as SectionNode).blocks
    : ((node as BlockNode).data as { blocks?: unknown } | undefined)?.blocks
  return Array.isArray(children) && (children as BlockNode[]).some((child) => child && typeof child === 'object' && containsUnavailable(child, 'block', isKnown))
}

/**
 * A move inside the SAME parent that would carry the node across an unavailable sibling
 * (changing the frozen node's order). Moves between parents never reorder survivors.
 */
export function moveCrossesFrozenSibling(payload: LayoutPayload | null | undefined, intent: DropIntent, isKnown: KnownType): boolean {
  if (intent.mode !== 'move' || !intent.sourceId || intent.action === 'inside') return false
  const siblings = siblingsOf(buildNodeTree(payload), intent.parentId)
  if (!siblings) return false
  const from = siblings.findIndex((node) => node.id === intent.sourceId)
  const target = siblings.findIndex((node) => node.id === intent.targetId)
  if (from < 0 || target < 0) return false
  const to = intent.action === 'before' ? target : target + 1
  const [lo, hi] = from < to ? [from + 1, to] : [to, from]
  return siblings.slice(lo, hi).some((node) => node.id !== intent.sourceId && isUnavailableType(node.kind, node.type, isKnown))
}

function siblingsOf(roots: ComponentNode[], parentId: string | null): ComponentNode[] | null {
  if (parentId === null) return roots
  const find = (nodes: ComponentNode[]): ComponentNode[] | null => {
    for (const node of nodes) {
      if (node.id === parentId) return node.children
      const hit = find(node.children)
      if (hit) return hit
    }
    return null
  }
  return find(roots)
}

/** A menu/keyboard move to final `index` under `parentId` that would cross an unavailable sibling. */
export function indexMoveCrossesFrozenSibling(
  payload: LayoutPayload | null | undefined,
  id: string,
  parentId: string | null,
  index: number,
  isKnown: KnownType,
): boolean {
  const siblings = siblingsOf(buildNodeTree(payload), parentId)
  if (!siblings) return false
  const from = siblings.findIndex((node) => node.id === id)
  if (from < 0) return false // arriving from another parent: no persisted order changes
  const [lo, hi] = from < index ? [from + 1, index + 1] : [index, from]
  return siblings.slice(lo, hi).some((node) => isUnavailableType(node.kind, node.type, isKnown))
}
