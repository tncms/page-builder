<script setup lang="ts">
// Phase 15 — Navigator panel.
//
// A searchable, collapsible tree of the whole layout (sections → blocks →
// nested blocks), synced both ways with the canvas: selecting or hovering here
// reflects in the canvas and vice-versa. Search filters by label/type/id and
// force-expands matches. Read-only over the payload — all edits go through the
// existing command actions on the node menu.
import { computed, provide, ref, watch } from 'vue'
import { useEditorStore } from '../../stores/editor'
import { filterNodeTree } from '../../utils/layout'
import { flattenVisible, resolveFocusFallback, type FlatNode } from '../../utils/navigatorFlatten'
import type { ComponentNode } from '../../types'
import { useNavigatorLocksStore } from '../../stores/navigatorLocks'
import { NavigatorFocusKey, NavigatorLocksKey, NavigatorMenuRequestKey, NavigatorRenameRequestKey, type NavigatorMenuRequest } from './injection'
import NavigatorNode from './NavigatorNode.vue'

const store = useEditorStore()
const query = ref('')

// Navigator v2 editor-session locks (Phase 26.3, Part 13): expose the owner's
// locked-id set to every row via provide (one computed for the whole tree), and
// drop stale ids whenever the tree changes — duplicate/paste mint new ids and
// delete removes them, so locks must never accumulate orphans.
const locks = useNavigatorLocksStore()
const lockedIds = computed<ReadonlySet<string>>(() => locks.lockedSet(store.owner))
provide(NavigatorLocksKey, lockedIds)

function collectIds(nodes: ComponentNode[], acc: string[] = []): string[] {
  for (const node of nodes) {
    acc.push(node.id)
    if (node.children.length) {
      collectIds(node.children, acc)
    }
  }
  return acc
}

watch(
  () => store.nodeTree,
  (tree) => locks.prune(store.owner, collectIds(tree)),
  { immediate: true },
)

function labelOf(node: ComponentNode): string {
  const def = store.definitionOf(node.kind, node.type)
  return (def?.label as string) || (def?.name as string) || node.type
}

const visibleTree = computed(() => filterNodeTree(store.nodeTree, query.value, labelOf))
const isFiltering = computed(() => query.value.trim() !== '')
const isEmpty = computed(() => store.nodeTree.length === 0)

// --- Navigator v2 keyboard model (Phase 26.3, Part 16/17) -------------------
// Focus is a roving `aria-activedescendant` pointer over the visible rows, kept
// DISTINCT from selection: Arrow/Home/End move focus, Enter/Space commits it to
// selection, Escape re-anchors focus to the selected node. A row must be visible
// to be focusable, so navigation walks the flattened, collapse-aware list.
const focusedId = ref<string | null>(null)

// Keyboard "open context menu" request (Part 4.1), consumed by the focused row.
const menuRequest = ref<NavigatorMenuRequest | null>(null)
provide(NavigatorMenuRequestKey, menuRequest)

function openContextMenu(): void {
  if (focusedId.value) {
    menuRequest.value = { id: focusedId.value, nonce: (menuRequest.value?.nonce ?? 0) + 1 }
  }
}

// Keyboard "begin rename" request (Increment 5, F2), consumed by the focused row.
const renameRequest = ref<NavigatorMenuRequest | null>(null)
provide(NavigatorRenameRequestKey, renameRequest)

function openRename(): void {
  if (focusedId.value) {
    renameRequest.value = { id: focusedId.value, nonce: (renameRequest.value?.nonce ?? 0) + 1 }
  }
}

function nodeExpanded(node: ComponentNode): boolean {
  // Mirrors NavigatorNode: filtering force-expands, otherwise honour collapse.
  return isFiltering.value || !store.isCollapsed(node.id)
}

const flat = computed<FlatNode[]>(() => flattenVisible(visibleTree.value, nodeExpanded))
const focusedDomId = computed(() => (focusedId.value ? `pb-nav-item-${focusedId.value}` : undefined))

provide(NavigatorFocusKey, {
  focusedId: computed(() => focusedId.value),
  focus: (id: string) => {
    focusedId.value = id
  },
})

function focusIndex(): number {
  return flat.value.findIndex((entry) => entry.id === focusedId.value)
}

function focusAt(index: number): void {
  const list = flat.value
  if (list.length === 0) {
    focusedId.value = null
    return
  }
  focusedId.value = list[Math.max(0, Math.min(index, list.length - 1))].id
}

function onTreeFocus(): void {
  if (focusedId.value === null || focusIndex() === -1) {
    focusedId.value = store.selectedComponentId ?? flat.value[0]?.id ?? null
  }
}

function onKeydown(event: KeyboardEvent): void {
  const list = flat.value
  if (list.length === 0) {
    return
  }
  const index = focusIndex()
  const current = index >= 0 ? list[index] : null

  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault()
      focusAt(index < 0 ? 0 : index + 1)
      break
    case 'ArrowUp':
      event.preventDefault()
      focusAt(index < 0 ? 0 : index - 1)
      break
    case 'Home':
      event.preventDefault()
      focusAt(0)
      break
    case 'End':
      event.preventDefault()
      focusAt(list.length - 1)
      break
    case 'ArrowRight':
      event.preventDefault()
      if (current?.hasChildren && !current.expanded) {
        store.toggleCollapse(current.id)
      } else if (current?.hasChildren && current.expanded) {
        focusAt(index + 1)
      }
      break
    case 'ArrowLeft':
      event.preventDefault()
      if (current?.hasChildren && current.expanded) {
        store.toggleCollapse(current.id)
      } else if (current) {
        const parentIndex = current.node.parentId ? list.findIndex((entry) => entry.id === current.node.parentId) : -1
        if (parentIndex >= 0) {
          focusAt(parentIndex)
        }
      }
      break
    case 'Enter':
    case ' ':
      event.preventDefault()
      if (current) {
        store.selectComponent(current.id)
      }
      break
    case 'Escape':
      event.preventDefault()
      focusedId.value = store.selectedComponentId ?? list[0]?.id ?? null
      break
    case 'F2':
      // Inline rename (Increment 5): the focused row owns its input.
      event.preventDefault()
      openRename()
      break
    case 'F10':
      if (event.shiftKey) {
        event.preventDefault()
        openContextMenu()
      }
      break
    case 'ContextMenu':
      event.preventDefault()
      openContextMenu()
      break
  }
}

// Keep focus stable when the visible set changes (collapse/filter/delete/cut):
// if the focused row disappears, fall back deterministically instead of vanishing.
let previousFlat: FlatNode[] = []
watch(
  flat,
  (next) => {
    if (focusedId.value !== null && !next.some((entry) => entry.id === focusedId.value)) {
      focusedId.value = resolveFocusFallback(previousFlat, next, focusedId.value)
    }
    previousFlat = next
  },
  { immediate: true },
)
</script>

<template>
  <div class="pb-editor-navigator">
    <div class="pb-editor-navigator__search">
      <input
        v-model="query"
        type="search"
        class="pb-editor-navigator__input"
        placeholder="Search components…"
        aria-label="Search components"
      />
    </div>

    <p v-if="isEmpty" class="pb-editor-navigator__empty">
      No components yet. Add a section to get started.
    </p>

    <p v-else-if="isFiltering && visibleTree.length === 0" class="pb-editor-navigator__empty">
      No components match “{{ query }}”.
    </p>

    <ul
      v-else
      class="pb-editor-navigator__tree"
      role="tree"
      tabindex="0"
      aria-label="Layout components"
      :aria-activedescendant="focusedDomId"
      @keydown="onKeydown"
      @focus="onTreeFocus"
    >
      <NavigatorNode
        v-for="node in visibleTree"
        :key="node.id"
        :node="node"
        :force-expand="isFiltering"
      />
    </ul>
  </div>
</template>
