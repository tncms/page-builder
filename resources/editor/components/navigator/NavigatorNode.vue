<script setup lang="ts">
// Phase 15 — Navigator tree node (recursive).
//
// One row per component: a collapse toggle, a kind glyph, the metadata label,
// the type, and a disabled flag — plus a per-node action menu (duplicate / copy
// / cut / paste / delete) that reuses the existing Phase 8 command actions.
// Click selects; double-click focuses the canvas; hover mirrors into the canvas
// hover state. Purely a view over store state — no layout mutation of its own.
import { computed, inject, nextTick, ref, watch } from 'vue'
import { useEditorStore } from '../../stores/editor'
import type { ComponentNode, DefinitionMeta } from '../../types'
import { describeNode } from '../../utils/navigatorDescriptor'
import { siblingPosition } from '../../utils/navigatorFlatten'
import { findComponentById } from '../../utils/layout'
import {
  canInsert,
  insertCandidates,
  resolveInsertTarget,
  type InsertPlacement,
  type InsertTarget,
} from '../../utils/navigatorInsert'
import { useNavigatorLocksStore } from '../../stores/navigatorLocks'
import { customNodeLabel, fallbackNodeLabel, NAV_LABEL_MAX } from '../../utils/nodeLabel'
import { NavigatorFocusKey, NavigatorLocksKey, NavigatorMenuRequestKey, NavigatorRenameRequestKey } from './injection'
import { resolveNavigatorActions, type NavigatorActionContext, type ResolvedNavigatorAction } from './navigatorActions'
import ComponentPicker from './ComponentPicker.vue'

const props = defineProps<{ node: ComponentNode; forceExpand?: boolean }>()

const store = useEditorStore()
const menuOpen = ref(false)

const hasChildren = computed(() => props.node.children.length > 0)
const expanded = computed(() => props.forceExpand || !store.isCollapsed(props.node.id))
const isSelected = computed(() => store.selectedComponentId === props.node.id)
const isHovered = computed(() => store.hoveredComponentId === props.node.id)
// Phase 24.2 §14 — only the currently selected component can hold a staged draft.
// Editor-session locks (Phase 26.3, Part 13) provided by the tree root; empty
// fallback so a standalone-mounted node still renders.
const lockedIds = inject(NavigatorLocksKey, computed((): ReadonlySet<string> => new Set()))

// Metadata descriptor (Phase 26.3, Part 4): the single deterministic source for
// this row's indicators — column width, staged-dirty dot, and lock.
const descriptor = computed(() =>
  describeNode(props.node, {
    selectedId: store.selectedComponentId,
    inspectorDirty: store.inspectorDirty,
    lockedIds: lockedIds.value,
  }),
)
const isStaged = computed(() => descriptor.value.isDirty)
const isLocked = computed(() => descriptor.value.isLocked)

// Keyboard focus pointer (Phase 26.3, Part 16/17), provided by the tree root and
// kept separate from selection. Clicking a row both focuses and selects it.
const navFocus = inject(NavigatorFocusKey, null)
const isFocused = computed(() => navFocus?.focusedId.value === props.node.id)
function selectNode(): void {
  store.selectComponent(props.node.id)
  navFocus?.focus(props.node.id)
}

// Lock authority (Phase 26.3, Part 13) — the registry's Lock/Unlock and the
// store's guards both read this store, so the menu and enforcement never diverge.
const locks = useNavigatorLocksStore()

// Move Up/Down (Part 4.1): one `move-component` reorder among siblings only. The
// mutator removes-then-inserts, so within a parent `index ± 1` is the exact target
// (removing the node shifts the next sibling down, so +1 lands after it). Disabled
// at the ends and for locked nodes; sections reorder at root (parentId = null).
const moveInfo = computed(() => siblingPosition(store.nodeTree, props.node.id))
const canMoveUp = computed(() => (moveInfo.value?.index ?? 0) > 0)
const canMoveDown = computed(() => {
  const info = moveInfo.value
  return info ? info.index < info.count - 1 : false
})

// Structural insertion availability (Increment 6). The parent's TYPE (null at the
// root) drives which candidates the NestingPolicy allows for Add Before/After; Add
// Inside targets this node itself. Availability is metadata- and policy-driven so
// a placement only shows when it has at least one valid candidate.
const parentType = computed<string | null>(() =>
  props.node.parentId ? findComponentById(store.currentPayload, props.node.parentId)?.type ?? null : null,
)
const insertAvailability = computed(() => ({
  inside: canInsert(props.node, 'inside', moveInfo.value, parentType.value, store.metadata),
  before: canInsert(props.node, 'before', moveInfo.value, parentType.value, store.metadata),
  after: canInsert(props.node, 'after', moveInfo.value, parentType.value, store.metadata),
}))
// The context-menu items are resolved from the action registry
// (navigatorActions.ts) against this row's live state. The template renders this
// list verbatim — all the "what shows / is enabled / does what" logic lives in
// the registry, keeping the view a pure renderer.
const actionContext = computed<NavigatorActionContext>(() => ({
  node: props.node,
  store,
  locks,
  isLocked: isLocked.value,
  moveInfo: moveInfo.value,
  canMoveUp: canMoveUp.value,
  canMoveDown: canMoveDown.value,
  commandLoading: store.commandLoading,
  beginRename: () => startRename(),
  insert: insertAvailability.value,
  beginInsert: (placement: InsertPlacement) => startInsert(placement),
}))
const menuItems = computed(() => resolveNavigatorActions(actionContext.value))

// Activate an item (mouse click or keyboard Enter/Space), then close. Enabled is
// the single guard — disabled items never run (and the button is `:disabled`, so
// a keyboard `.click()` is a no-op too). If the action started inline rename it
// now owns focus, so we close the menu WITHOUT restoring focus to the tree.
function activate(item: ResolvedNavigatorAction): void {
  if (!item.enabled) return
  void item.execute()
  // If the action opened inline rename or the component picker, that surface now
  // owns focus — close the menu WITHOUT restoring focus to the tree.
  if (renaming.value || inserting.value) {
    closeMenu()
    return
  }
  closeMenuAndRestore()
}

// Keyboard Shift+F10 / ContextMenu (Part 4.1): the tree root requests this row's
// menu by id; open it when the request targets us.
const menuRequest = inject(NavigatorMenuRequestKey, null)
watch(
  () => menuRequest?.value,
  (request) => {
    if (request && request.id === props.node.id) {
      openMenu(true)
    }
  },
)

// ---- Inline rename (Increment 5) -------------------------------------------
// The row swaps its label span for a text input while editing. Typing mutates
// only local draft state (zero commands); Enter/Tab commit through the store's
// renameComponent (one command / one undo), Escape restores the label with no
// command. Locked rows never enter rename. All commit logic lives in the store,
// not here — this block only manages the input's UI state and focus.
const renaming = ref(false)
const draftName = ref('')
const inputEl = ref<HTMLInputElement | null>(null)
let renameOrigin: HTMLElement | null = null
let renameSettling = false

// F2 request from the tree root, targeted at the focused row.
const renameRequest = inject(NavigatorRenameRequestKey, null)
watch(
  () => renameRequest?.value,
  (request) => {
    if (request && request.id === props.node.id) startRename()
  },
)

function startRename(): void {
  if (isLocked.value || renaming.value) return
  renameOrigin = typeof document !== 'undefined' ? (document.activeElement as HTMLElement | null) : null
  if (menuOpen.value) closeMenu()
  // Point roving focus at this row without changing selection (spec).
  navFocus?.focus(props.node.id)
  draftName.value = label.value
  renaming.value = true
  void nextTick(() => {
    inputEl.value?.focus()
    inputEl.value?.select()
  })
}

function restoreRenameFocus(): void {
  const origin = renameOrigin
  renameOrigin = null
  void nextTick(() => {
    if (origin && origin.isConnected && typeof origin.focus === 'function') {
      origin.focus()
      return
    }
    const tree = document.getElementById(`pb-nav-item-${props.node.id}`)?.closest('[role="tree"]') as HTMLElement | null
    tree?.focus()
  })
}

async function commitRename(): Promise<void> {
  if (!renaming.value || renameSettling) return
  renameSettling = true
  const ok = await store.renameComponent(props.node.id, draftName.value)
  renameSettling = false
  // On lock/dirty-guard block (ok === false), keep editing — never lose the draft.
  if (ok) {
    renaming.value = false
    restoreRenameFocus()
  }
}

function cancelRename(): void {
  renaming.value = false
  restoreRenameFocus()
}

function onRenameKeydown(e: KeyboardEvent): void {
  if (e.key === 'Enter' || e.key === 'Tab') {
    e.preventDefault()
    void commitRename()
  } else if (e.key === 'Escape') {
    e.preventDefault()
    cancelRename()
  }
}

// ---- Structural insertion (Increment 6) ------------------------------------
// Add Inside / Before / After open a reusable component picker; picking commits
// through the store's single `insertComponent` command (one undo, server selects
// the new node). All placement + nesting logic lives in utils/navigatorInsert and
// the store — this block only manages the picker's UI state and focus handoff.
const inserting = ref(false)
const insertTitle = ref('')
const insertCandidateList = ref<DefinitionMeta[]>([])
let pendingInsertTarget: InsertTarget | null = null
let insertOrigin: HTMLElement | null = null

const PLACEMENT_TITLES: Record<InsertPlacement, string> = {
  inside: 'Add inside',
  before: 'Add before',
  after: 'Add after',
}

function startInsert(placement: InsertPlacement): void {
  if (isLocked.value || inserting.value) return
  const target = resolveInsertTarget(props.node, placement, moveInfo.value, parentType.value)
  if (!target) return
  const candidates = insertCandidates(store.metadata, target.parentType)
  if (candidates.length === 0) return
  pendingInsertTarget = target
  insertCandidateList.value = candidates
  insertTitle.value = PLACEMENT_TITLES[placement]
  insertOrigin = typeof document !== 'undefined' ? (document.activeElement as HTMLElement | null) : null
  if (menuOpen.value) closeMenu()
  // Point roving focus at this row without changing selection (mirrors rename).
  navFocus?.focus(props.node.id)
  inserting.value = true
}

async function chooseInsert(def: DefinitionMeta): Promise<void> {
  const target = pendingInsertTarget
  closeInsert(false) // the new row will take focus; skip origin restore
  if (!target) return
  const newId = await store.insertComponent({
    parentId: target.parentId,
    componentType: def.type,
    index: target.index,
  })
  if (!newId) return
  // Reveal the new child if it landed inside a collapsed parent, then move
  // Navigator focus to it and scroll its row into view (selection is set by the
  // store from the server's selection result).
  if (target.parentId) store.expandComponent(target.parentId)
  navFocus?.focus(newId)
  void nextTick(() => {
    document.getElementById(`pb-nav-item-${newId}`)?.scrollIntoView?.({ block: 'nearest' })
  })
}

function closeInsert(restore = true): void {
  if (!inserting.value) return
  inserting.value = false
  insertCandidateList.value = []
  pendingInsertTarget = null
  const origin = insertOrigin
  insertOrigin = null
  if (!restore) return
  void nextTick(() => {
    if (origin && origin.isConnected && typeof origin.focus === 'function') {
      origin.focus()
      return
    }
    const tree = document.getElementById(`pb-nav-item-${props.node.id}`)?.closest('[role="tree"]') as HTMLElement | null
    tree?.focus()
  })
}

// The definition (type) label used when a node has no custom name (Increment 5).
const fallbackLabel = computed(() => fallbackNodeLabel(store.definitionOf(props.node.kind, props.node.type), props.node.type))
// The label shown in the row: a custom rename (data._label) wins, else the type.
const label = computed(() => customNodeLabel(props.node) ?? fallbackLabel.value)

const iconName = computed(() => (store.definitionOf(props.node.kind, props.node.type)?.icon as string) ?? '')

// PB-TEMPLATE-REGISTRY-2-D — an unknown (unregistered-type) node keeps its place in the tree;
// it only gains an accessible "Unavailable" badge. Recognition is the store's registry adapter.
const unknown = computed(() => !store.isKnownType(props.node.kind, props.node.type))
const unknownBadge = computed(() => store.uiString('unknownBadge', 'Unavailable'))

// Phase 25.3.1 §10 — layout nodes surface extra context in the tree: a column
// shows its width, a container/column shows its child count (or "empty").
const isColumn = computed(() => props.node.type === 'column')
const isLayoutNode = computed(() => props.node.type === 'container' || props.node.type === 'column')

const columnWidth = computed(() => descriptor.value.columnWidth ?? '')

const childSummary = computed(() =>
  props.node.children.length === 0 ? 'empty' : `${props.node.children.length} item${props.node.children.length === 1 ? '' : 's'}`,
)

// Heroicon names have no client-side SVG here; use a compact kind glyph instead.
const glyph = computed(() => {
  if (props.node.kind === 'section') return '▤'
  return hasChildren.value ? '▧' : '▪'
})

function toggle(e: Event) {
  e.stopPropagation()
  if (hasChildren.value) store.toggleCollapse(props.node.id)
}

// ---- Context-menu focus + keyboard roving (Phase 26.3, Increment 4.2) ------
// The menu is a roving-tabindex `role="menu"`: exactly one item is focused at a
// time. Opening via keyboard (Shift+F10 / ContextMenu) moves focus to the first
// enabled item; opening via mouse leaves focus put so the pointer user isn't
// yanked. On close we restore focus to whoever owned it at open (the roving tree
// container), so keyboard users never get stranded on a now-hidden button.
const menuEl = ref<HTMLElement | null>(null)
let menuOrigin: HTMLElement | null = null

function openMenu(viaKeyboard: boolean): void {
  menuOrigin = typeof document !== 'undefined' ? (document.activeElement as HTMLElement | null) : null
  menuOpen.value = true
  if (viaKeyboard) {
    void nextTick(() => focusItem(0))
  }
}

function closeMenu() {
  menuOpen.value = false
}

// Close and hand focus back to the originating row/tree (Escape, activation, Tab).
function closeMenuAndRestore(): void {
  menuOpen.value = false
  const origin = menuOrigin
  menuOrigin = null
  void nextTick(() => {
    if (origin && origin.isConnected && typeof origin.focus === 'function') origin.focus()
  })
}

// Live-queried so disabled items (locked / command-loading / boundary) are always
// skipped by roving — the disabled set changes reactively as state changes.
function enabledItems(): HTMLButtonElement[] {
  const root = menuEl.value
  if (!root) return []
  return Array.from(root.querySelectorAll<HTMLButtonElement>('button[role="menuitem"]')).filter((b) => !b.disabled)
}

function focusItem(index: number): void {
  const items = enabledItems()
  if (items.length === 0) return
  const n = items.length
  // Arrows wrap; Home/End pass 0 / n-1 which clamp naturally.
  const wrapped = ((index % n) + n) % n
  items[wrapped].focus()
}

function activeItemIndex(): number {
  const active = typeof document !== 'undefined' ? document.activeElement : null
  return enabledItems().indexOf(active as HTMLButtonElement)
}

function onMenuKeydown(e: KeyboardEvent): void {
  switch (e.key) {
    case 'ArrowDown': {
      e.preventDefault()
      const i = activeItemIndex()
      focusItem(i < 0 ? 0 : i + 1)
      break
    }
    case 'ArrowUp': {
      e.preventDefault()
      const i = activeItemIndex()
      focusItem(i < 0 ? enabledItems().length - 1 : i - 1)
      break
    }
    case 'Home':
      e.preventDefault()
      focusItem(0)
      break
    case 'End':
      e.preventDefault()
      focusItem(enabledItems().length - 1)
      break
    case 'Enter':
    case ' ': {
      e.preventDefault()
      const active = document.activeElement as HTMLButtonElement | null
      if (active && active.getAttribute('role') === 'menuitem' && !active.disabled) active.click()
      break
    }
    case 'Escape':
      e.preventDefault()
      e.stopPropagation()
      closeMenuAndRestore()
      break
    case 'Tab':
      // Never let Tab strand focus behind an open menu.
      e.preventDefault()
      closeMenuAndRestore()
      break
  }
}
</script>

<template>
  <li class="pb-editor-navigator-node">
    <div
      :id="`pb-nav-item-${node.id}`"
      class="pb-editor-navigator-row"
      :class="{
        'is-selected': isSelected,
        'is-hovered': isHovered,
        'is-disabled': node.disabled,
        'is-focused': isFocused,
      }"
      role="treeitem"
      :aria-level="node.depth + 1"
      :aria-selected="isSelected"
      :aria-expanded="hasChildren ? expanded : undefined"
      @click.stop="selectNode()"
      @dblclick.stop="store.focusComponent(node.id)"
      @mouseenter="store.hoverComponent(node.id)"
      @mouseleave="store.hoverComponent(null)"
    >
      <button
        v-if="hasChildren"
        type="button"
        class="pb-editor-navigator-toggle"
        :aria-expanded="expanded"
        :title="expanded ? 'Collapse' : 'Expand'"
        @click="toggle"
      >
        {{ expanded ? '▾' : '▸' }}
      </button>
      <span v-else class="pb-editor-navigator-toggle pb-editor-navigator-toggle--leaf" />

      <span class="pb-editor-navigator-glyph" :title="iconName || node.kind">{{ glyph }}</span>
      <input
        v-if="renaming"
        ref="inputEl"
        v-model="draftName"
        type="text"
        role="textbox"
        class="pb-editor-navigator-label pb-editor-navigator-rename"
        :aria-label="`Rename ${fallbackLabel}`"
        :maxlength="NAV_LABEL_MAX"
        @click.stop
        @dblclick.stop
        @keydown.stop="onRenameKeydown"
        @blur="commitRename"
      />
      <span v-else class="pb-editor-navigator-label" @dblclick.stop.prevent="startRename()">{{ label }}</span>
      <span
        v-if="isStaged"
        class="pb-editor-navigator-staged"
        title="Unapplied changes"
        aria-label="Unapplied changes"
      ></span>
      <code class="pb-editor-navigator-type">{{ node.type }}</code>
      <span v-if="isColumn" class="pb-editor-navigator-meta pb-editor-navigator-meta--width">{{ columnWidth }}</span>
      <span v-if="isLayoutNode" class="pb-editor-navigator-meta pb-editor-navigator-meta--count">{{ childSummary }}</span>
      <span v-if="node.disabled" class="pb-editor-navigator-flag">off</span>
      <span v-if="isLocked" class="pb-editor-navigator-flag pb-editor-navigator-flag--locked" aria-label="Locked" title="Locked">locked</span>
      <span
        v-if="unknown"
        class="pb-editor-navigator-flag pb-editor-navigator-flag--unknown"
        :aria-label="unknownBadge"
        :title="unknownBadge"
        data-testid="navigator-unknown-badge"
      >{{ unknownBadge }}</span>

      <button
        type="button"
        class="pb-editor-navigator-locate"
        title="Reveal on canvas"
        @click.stop="store.focusComponent(node.id)"
      >
        ⊙
      </button>
      <button
        type="button"
        class="pb-editor-navigator-menu-btn"
        title="Actions"
        aria-haspopup="menu"
        :aria-expanded="menuOpen"
        @click.stop="menuOpen ? closeMenuAndRestore() : openMenu(false)"
      >
        ⋯
      </button>

      <div
        v-if="menuOpen"
        ref="menuEl"
        class="pb-editor-navigator-menu"
        role="menu"
        @mouseleave="closeMenu"
        @keydown="onMenuKeydown"
      >
        <!-- Pure renderer: items come from the action registry (navigatorActions.ts);
             this template holds no business logic, only render + keyboard nav. -->
        <button
          v-for="item in menuItems"
          :key="item.id"
          type="button"
          role="menuitem"
          :class="{ 'pb-editor-navigator-menu__danger': item.danger }"
          :disabled="!item.enabled"
          :title="item.disabledReason"
          @click.stop="activate(item)"
        >
          {{ item.label }}
        </button>
      </div>

      <!-- Structural insertion picker (Increment 6): reusable, metadata-driven,
           NestingPolicy-filtered upstream. Picking commits via insertComponent. -->
      <ComponentPicker
        v-if="inserting"
        :candidates="insertCandidateList"
        :title="insertTitle"
        @pick="chooseInsert"
        @close="closeInsert()"
      />
    </div>

    <ul v-if="hasChildren && expanded" class="pb-editor-navigator-children">
      <NavigatorNode
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :force-expand="forceExpand"
      />
    </ul>
  </li>
</template>
