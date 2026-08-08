// Read-only layout traversal helpers (Phase 10).
//
// The canvas renders the layout as a node tree but the LayoutPayload remains the
// single source of truth — these helpers only *derive* a view of it. Nesting
// mirrors the backend model: a section's children are its blocks[]; a block's
// children are its data.blocks[] (container blocks).

import { toRaw } from 'vue'
import type {
  BlockNode,
  ComponentKind,
  ComponentMeta,
  ComponentNode,
  LayoutPayload,
  SectionNode,
} from '../types'

/** The stable id of a raw section/block node, or null when absent. */
export function getComponentId(node: SectionNode | BlockNode): string | null {
  return typeof node.id === 'string' && node.id !== '' ? node.id : null
}

/** Whether a node is toggled off (display-only styling; no behavior in Phase 10). */
export function isNodeDisabled(data: Record<string, unknown> | undefined): boolean {
  if (!data) return false
  return data.disabled === true || data.enabled === false
}

function childBlocks(data: Record<string, unknown> | undefined): BlockNode[] {
  const blocks = data?.blocks
  return Array.isArray(blocks) ? (blocks as BlockNode[]) : []
}

function blockToNode(block: BlockNode, parentId: string | null, depth: number): ComponentNode {
  const data = block.data ?? {}
  return {
    id: getComponentId(block) ?? '',
    type: block.type,
    kind: 'block',
    parentId,
    depth,
    disabled: isNodeDisabled(data),
    data,
    children: childBlocks(data).map((child) => blockToNode(child, getComponentId(block), depth + 1)),
  }
}

function sectionToNode(section: SectionNode, depth: number): ComponentNode {
  const data = section.data ?? {}
  const id = getComponentId(section)
  return {
    id: id ?? '',
    type: section.type,
    kind: 'section',
    parentId: null,
    depth,
    disabled: isNodeDisabled(data),
    data,
    children: (section.blocks ?? []).map((block) => blockToNode(block, id, depth + 1)),
  }
}

/** Build the section→block→nested-block node tree for the canvas. */
export function buildNodeTree(payload: LayoutPayload | null | undefined): ComponentNode[] {
  return (payload?.sections ?? []).map((section) => sectionToNode(section, 0))
}

export interface PathItem {
  id: string
  type: string
  kind: ComponentKind
}

/**
 * The trail of components from the root section down to (and including) `id`.
 * Powers the breadcrumb: `[Section, Container, Button]`. Empty when not found.
 */
export function componentPath(payload: LayoutPayload | null | undefined, id: string | null): PathItem[] {
  if (!id) return []

  const walk = (nodes: ComponentNode[], trail: PathItem[]): PathItem[] | null => {
    for (const node of nodes) {
      const next = [...trail, { id: node.id, type: node.type, kind: node.kind }]
      if (node.id === id) return next
      const found = walk(node.children, next)
      if (found) return found
    }
    return null
  }

  return walk(buildNodeTree(payload), []) ?? []
}

/** Just the ancestors of `id` (the path without the component itself). */
export function componentAncestors(payload: LayoutPayload | null | undefined, id: string | null): PathItem[] {
  return componentPath(payload, id).slice(0, -1)
}

/**
 * Prune a node tree to entries whose id/type/label match `query`, or that have a
 * matching descendant. A matching node keeps its full subtree; a non-matching
 * ancestor keeps only the matching branches. Empty query returns the tree as-is.
 */
export function filterNodeTree(
  nodes: ComponentNode[],
  query: string,
  labelOf: (node: ComponentNode) => string,
): ComponentNode[] {
  const q = query.trim().toLowerCase()
  if (!q) return nodes

  const matches = (node: ComponentNode): boolean =>
    node.id.toLowerCase().includes(q) ||
    node.type.toLowerCase().includes(q) ||
    labelOf(node).toLowerCase().includes(q)

  const prune = (list: ComponentNode[]): ComponentNode[] => {
    const out: ComponentNode[] = []
    for (const node of list) {
      if (matches(node)) {
        out.push(node) // keep the whole subtree so context is visible
        continue
      }
      const children = prune(node.children)
      if (children.length) out.push({ ...node, children })
    }
    return out
  }

  return prune(nodes)
}

/** Find a component by id anywhere in the payload, returning its metadata. */
export function findComponentById(
  payload: LayoutPayload | null | undefined,
  id: string | null,
): ComponentMeta | null {
  if (!id) return null

  const walk = (nodes: ComponentNode[]): ComponentMeta | null => {
    for (const node of nodes) {
      if (node.id === id) {
        return {
          id: node.id,
          type: node.type,
          kind: node.kind,
          parentId: node.parentId,
          disabled: node.disabled,
        }
      }
      const found = walk(node.children)
      if (found) return found
    }
    return null
  }

  return walk(buildNodeTree(payload))
}

export interface RawNodeHit {
  node: SectionNode | BlockNode
  kind: ComponentKind
  parentId: string | null
  canContainChildren: boolean
}

/** Whether a node may hold child blocks (sections always; blocks if containers). */
export function canContainChildren(node: SectionNode | BlockNode, kind: ComponentKind): boolean {
  if (kind === 'section') return true
  return Array.isArray((node as BlockNode).data?.blocks)
}

/**
 * Find the RAW section/block node by id (a deep clone, safe to stash on a
 * clipboard and re-insert). Returns its kind, parent id and container capability.
 */
export function findComponentNode(
  payload: LayoutPayload | null | undefined,
  id: string | null,
): RawNodeHit | null {
  if (!id) return null

  const searchBlocks = (blocks: BlockNode[], parentId: string | null): RawNodeHit | null => {
    for (const block of blocks) {
      if (getComponentId(block) === id) {
        return { node: clone(block), kind: 'block', parentId, canContainChildren: canContainChildren(block, 'block') }
      }
      const nested = Array.isArray(block.data?.blocks) ? (block.data!.blocks as BlockNode[]) : []
      const hit = searchBlocks(nested, getComponentId(block))
      if (hit) return hit
    }
    return null
  }

  for (const section of payload?.sections ?? []) {
    if (getComponentId(section) === id) {
      return { node: clone(section), kind: 'section', parentId: null, canContainChildren: true }
    }
    const hit = searchBlocks(section.blocks ?? [], getComponentId(section))
    if (hit) return hit
  }

  return null
}

// Deep clone via toRaw + JSON. structuredClone throws DataCloneError on Vue
// reactive proxies in real browsers (and happy-dom), so it must NOT be used on
// reactive payloads. toRaw unwraps the proxy; JSON strips any remaining
// reactivity/refs and guarantees a plain, JSON-safe copy.
function clone<T>(value: T): T {
  return JSON.parse(JSON.stringify(toRaw(value ?? ({} as T)))) as T
}

export interface ComponentLocation {
  parentId: string | null
  index: number
  kind: ComponentKind
}

/**
 * Where a component sits in the payload: its parent id (null at the root) and
 * its index within that parent's children. Drag/drop uses this to translate a
 * "before/after this sibling" drop into the numeric index the move command wants
 * (Phase 14). Returns null when the id isn't found.
 */
export function componentLocation(
  payload: LayoutPayload | null | undefined,
  id: string | null,
): ComponentLocation | null {
  if (!id) return null

  const searchBlocks = (blocks: BlockNode[], parentId: string | null): ComponentLocation | null => {
    for (let i = 0; i < blocks.length; i++) {
      const block = blocks[i]
      if (getComponentId(block) === id) return { parentId, index: i, kind: 'block' }
      const nested = Array.isArray(block.data?.blocks) ? (block.data!.blocks as BlockNode[]) : []
      const hit = searchBlocks(nested, getComponentId(block))
      if (hit) return hit
    }
    return null
  }

  const sections = payload?.sections ?? []
  for (let i = 0; i < sections.length; i++) {
    if (getComponentId(sections[i]) === id) return { parentId: null, index: i, kind: 'section' }
    const hit = searchBlocks(sections[i].blocks ?? [], getComponentId(sections[i]))
    if (hit) return hit
  }

  return null
}

/**
 * Whether a component can hold child blocks: sections always can; a block can
 * when its `data.blocks` is an array (a container block, even when empty).
 * The rendered marker never carries this — its kind is always 'section'/'block'
 * (BlockInstance::kind() has no 'container') — so drag/drop must consult the
 * payload to know a container can receive a drop. Read-only, no cloning.
 */
export function isContainerId(payload: LayoutPayload | null | undefined, id: string | null): boolean {
  if (!id) return false

  const scan = (blocks: BlockNode[]): boolean | null => {
    for (const block of blocks) {
      if (getComponentId(block) === id) return Array.isArray(block.data?.blocks)
      const nested = Array.isArray(block.data?.blocks) ? (block.data!.blocks as BlockNode[]) : []
      const hit = scan(nested)
      if (hit !== null) return hit
    }
    return null
  }

  for (const section of payload?.sections ?? []) {
    if (getComponentId(section) === id) return true
    const hit = scan(section.blocks ?? [])
    if (hit !== null) return hit
  }
  return false
}

/**
 * Number of direct children a parent holds (root sections when parentId is null).
 * Read-only traversal — no cloning, so it is safe to call on reactive payloads.
 */
export function childrenCount(payload: LayoutPayload | null | undefined, parentId: string | null): number {
  if (parentId === null) return payload?.sections?.length ?? 0

  const countIn = (blocks: BlockNode[]): number | null => {
    for (const block of blocks) {
      if (getComponentId(block) === parentId) {
        return Array.isArray(block.data?.blocks) ? (block.data!.blocks as BlockNode[]).length : 0
      }
      const nested = Array.isArray(block.data?.blocks) ? (block.data!.blocks as BlockNode[]) : []
      const hit = countIn(nested)
      if (hit !== null) return hit
    }
    return null
  }

  for (const section of payload?.sections ?? []) {
    if (getComponentId(section) === parentId) return (section.blocks ?? []).length
    const hit = countIn(section.blocks ?? [])
    if (hit !== null) return hit
  }
  return 0
}

/** Ids of the direct children of `parentId` (root section ids when null). */
export function childIds(payload: LayoutPayload | null | undefined, parentId: string | null): string[] {
  const tree = buildNodeTree(payload)
  if (parentId === null) return tree.map((node) => node.id)

  const find = (nodes: ComponentNode[]): ComponentNode | null => {
    for (const node of nodes) {
      if (node.id === parentId) return node
      const hit = find(node.children)
      if (hit) return hit
    }
    return null
  }

  return find(tree)?.children.map((child) => child.id) ?? []
}

/**
 * Which component should be selected after `deletedId` is removed. Preference:
 * next sibling → previous sibling → parent → null. A null result clears the
 * selection (the deleted node was the only root section). Shared by every delete
 * surface (navigator menu, canvas toolbars, keyboard) so the fallback is
 * identical everywhere.
 */
export function selectionAfterDelete(
  payload: LayoutPayload | null | undefined,
  deletedId: string,
): string | null {
  const loc = componentLocation(payload, deletedId)
  if (!loc) return null

  const siblings = childIds(payload, loc.parentId)
  const next = siblings[loc.index + 1]
  if (next && next !== deletedId) return next

  if (loc.index - 1 >= 0) {
    const prev = siblings[loc.index - 1]
    if (prev && prev !== deletedId) return prev
  }

  return loc.parentId
}
