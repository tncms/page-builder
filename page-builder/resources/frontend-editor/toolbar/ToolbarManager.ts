// Phase 22 — In-canvas component toolbar orchestrator (shell, Pinia).
//
// Holds only the toolbar's own UI state (menu open, active sidebar tab, the
// selected component's reported geometry, last-computed placement). Every
// structural action delegates to the SHARED editor store's existing command bus
// (executeCommand / duplicate / copy / cut / paste / beginDrag) — this store
// never hand-edits the payload. Destructive/structural actions flush any open
// inline edit first so nothing typed-but-uncommitted is lost.
//
// Visibility is a getter derived from the editor / inline / box-model stores, so
// the toolbar hides itself during inline editing, drag, and box-model drag with
// no manual synchronisation.

import { defineStore } from 'pinia'
import { useEditorStore } from '../../editor/stores/editor'
import { useInlineStore } from '../inline/InlineManager'
import { useBoxModelStore } from '../box-model/BoxModelManager'
import { useLiveDragStore } from '../drag/LiveDragManager'
import { isContainerId, childrenCount } from '../../editor/utils/layout'
import { insertCandidates } from '../../editor/utils/navigatorInsert'
import { pbDebugEnabled } from '../debug'
import type { DefinitionMeta } from '../../editor/types'
import { moveIndex, siblingInsert, canMoveUp, canMoveDown } from './ToolbarActions'
import { resolveActions, type ActionContext, type ActionKind, type ToolbarAction } from './actionModel'
import { emptyColumn, newContainer } from './layoutNodes'
import type { ComponentGeometry, SidebarTab, ToolbarPlacement } from './types'

interface ToolbarState {
  geometry: ComponentGeometry | null
  menuOpen: boolean
  activeSidebarTab: SidebarTab
  // Phase 24.2 §13 — the user manually chose a sidebar tab; selecting a component
  // must then NOT force-switch to Options (respect Navigator/Elements work).
  sidebarTabLockedByUser: boolean
  placement: ToolbarPlacement
  // Phase 26.1 — the layout-preset popup is open for the current selection.
  layoutPopupOpen: boolean
  // Phase 26.3.10 — the "Add Element" component picker is open for the current selection.
  addElementPickerOpen: boolean
}

export const useToolbarStore = defineStore('pbToolbar', {
  state: (): ToolbarState => ({
    geometry: null,
    menuOpen: false,
    activeSidebarTab: 'elements',
    sidebarTabLockedByUser: false,
    placement: 'above',
    layoutPopupOpen: false,
    addElementPickerOpen: false,
  }),

  getters: {
    // The toolbar shows only for a selected component whose geometry we hold,
    // and never while another direct-manipulation tool owns the pointer.
    visible(state): boolean {
      const editor = useEditorStore()
      const inline = useInlineStore()
      const box = useBoxModelStore()
      const liveDrag = useLiveDragStore()
      const selected = editor.selectedComponentId
      return (
        selected != null &&
        state.geometry?.componentId === selected &&
        !inline.active &&
        !editor.isDragging &&
        !box.dragging &&
        !liveDrag.dragging
      )
    },

    // Human label ("Hero"), derived from the definition metadata, falling back to
    // the reported type. Never the raw id.
    label(state): string {
      const editor = useEditorStore()
      const sel = editor.selectedComponent
      if (!sel) return ''
      const def = editor.definitionOf(sel.kind, sel.type)
      const fromDef = (def?.label as string) || (def?.name as string) || ''
      if (fromDef) return fromDef
      const type = sel.type || state.geometry?.type || ''
      return type ? type.charAt(0).toUpperCase() + type.slice(1) : ''
    },

    // "Section" / "Block" for the selected component.
    kindLabel(): string {
      const editor = useEditorStore()
      const kind = editor.selectedComponent?.kind
      return kind === 'section' ? 'Section' : kind === 'block' ? 'Block' : ''
    },

    typeLabel(): string {
      const editor = useEditorStore()
      return editor.selectedComponent?.type ?? ''
    },

    // Phase 26.3.10 — component-picker candidates for the selected container,
    // filtered by the same nesting policy the Navigator "Add Inside" picker uses.
    addElementCandidates(): DefinitionMeta[] {
      const editor = useEditorStore()
      const sel = editor.selectedComponent
      if (!sel || !editor.metadata) return []
      return insertCandidates(editor.metadata, sel.type)
    },

    // Ancestors of the selection (root → parent), for the breadcrumb.
    breadcrumb() {
      const editor = useEditorStore()
      return editor.selectedPath.slice(0, -1)
    },

    hasClipboard(): boolean {
      return useEditorStore().hasClipboard
    },

    canSelectParent(): boolean {
      return useEditorStore().selectedComponent?.parentId != null
    },

    // Paste-inside only makes sense into a section or a container block.
    canPasteInside(): boolean {
      const editor = useEditorStore()
      const sel = editor.selectedComponent
      if (!sel || !editor.hasClipboard) return false
      return sel.kind === 'section' || isContainerId(editor.currentPayload, sel.id)
    },

    canMoveUp(): boolean {
      const editor = useEditorStore()
      return canMoveUp(editor.currentPayload, editor.selectedComponentId)
    },

    canMoveDown(): boolean {
      const editor = useEditorStore()
      return canMoveDown(editor.currentPayload, editor.selectedComponentId)
    },

    // ---- Phase 26.1 — type-aware action model -------------------------
    // Resolve the selection into an action "kind": a column node (type 'column')
    // and a container node are both distinct from a plain section or leaf block.
    actionKind(): ActionKind {
      const editor = useEditorStore()
      const sel = editor.selectedComponent
      if (!sel) return 'block'
      if (sel.kind === 'section') return 'section'
      if (sel.type === 'column') return 'column'
      if (isContainerId(editor.currentPayload, sel.id)) return 'container'
      return 'block'
    },

    // The descriptor context passed to every action's when()/run(): selection
    // facts + store delegates. Delegates close over `this` so the model stays pure.
    actionContext(): ActionContext {
      const editor = useEditorStore()
      const self = this
      return {
        kind: self.actionKind,
        componentId: editor.selectedComponentId,
        canMoveUp: self.canMoveUp,
        canMoveDown: self.canMoveDown,
        duplicate: () => void self.duplicateSelected(),
        copy: () => self.copySelected(),
        cut: () => void self.cutSelected(),
        delete: () => void self.deleteSelectedWithFallback(),
        moveUp: () => void self.moveSelectedUp(),
        moveDown: () => void self.moveSelectedDown(),
        openOptions: () => self.openOptionsForSelected(),
        openLayoutPopup: () => self.openLayoutPopup(),
        addColumn: () => void self.addColumnToSelected(),
        addContainer: () => void self.addContainerToSelected(),
        addElement: () => void self.addElementToSelected(),
        reverseColumns: () => void self.reverseColumnsOnSelected(),
        patchData: (data) => void self.patchSelectedData(data),
        data: editor.selectedComponent?.data ?? {},
      }
    },

    // The actions that apply to the current selection, in table order.
    resolvedActions(): ToolbarAction[] {
      return resolveActions(this.actionContext)
    },

    // The current column count of the selected container (for the popup's
    // content-loss confirm). 0 for non-containers.
    selectedColumnCount(): number {
      const editor = useEditorStore()
      const id = editor.selectedComponentId
      if (!id) return 0
      return childrenCount(editor.currentPayload, id)
    },
  },

  actions: {
    // ---- Geometry + placement (reported by the iframe) ----------------
    setGeometry(geometry: ComponentGeometry) {
      this.geometry = geometry
    },
    clearGeometry() {
      this.geometry = null
    },
    setPlacement(placement: ToolbarPlacement) {
      this.placement = placement
    },

    // ---- More menu ----------------------------------------------------
    openMenu() {
      this.menuOpen = true
    },
    closeMenu() {
      this.menuOpen = false
    },
    toggleMenu() {
      this.menuOpen = !this.menuOpen
    },

    // ---- Sidebar tab (Edit / Options) ---------------------------------
    // Low-level setter (used by automatic selection-driven switches).
    setSidebarTab(tab: SidebarTab) {
      this.activeSidebarTab = tab
    },

    // Phase 24.2 §13 — a deliberate tab click: lock the choice so selecting a
    // component won't yank the user back to Options.
    selectSidebarTab(tab: SidebarTab) {
      this.activeSidebarTab = tab
      this.sidebarTabLockedByUser = true
    },

    /** Edit / Options: reveal the Options tab for the (already selected) node. */
    openOptionsForSelected() {
      const editor = useEditorStore()
      if (!editor.selectedComponentId) return
      this.activeSidebarTab = 'options'
      // An explicit Edit/Options intent clears the manual lock.
      this.sidebarTabLockedByUser = false
      this.closeMenu()
    },

    // ---- Primary actions (delegate to the shared command bus) ---------
    copySelected() {
      useEditorStore().copySelected()
      this.closeMenu()
    },

    async cutSelected() {
      const editor = useEditorStore()
      await editor.flushInline()
      await editor.cutSelected()
      this.closeMenu()
    },

    async duplicateSelected() {
      const editor = useEditorStore()
      await editor.flushInline()
      editor.duplicateSelected()
      this.closeMenu()
    },

    /** Delete the selection via the shared store action (fallback lives there, §4). */
    async deleteSelectedWithFallback() {
      const editor = useEditorStore()
      const id = editor.selectedComponentId
      if (!id) return
      await editor.flushInline()
      await editor.deleteComponent(id)
      this.closeMenu()
    },

    /**
     * Begin a cross-iframe drag from the toolbar handle (Phase 22.1). Flushes any
     * open inline edit and cancels a box-model session first, then hands the
     * selected component to the Live Builder drag engine (NOT the structure
     * editor's DragManager, which is bound to the visual canvas, not the iframe).
     */
    async beginDragSelected(event: PointerEvent) {
      const editor = useEditorStore()
      const id = editor.selectedComponentId
      if (!id) return
      await editor.flushInline()
      useBoxModelStore().cancel()
      this.closeMenu()
      useLiveDragStore().startFromComponent(id, event)
    },

    // ---- More-menu actions --------------------------------------------
    async moveSelectedUp() {
      await this.moveSelected('up')
    },
    async moveSelectedDown() {
      await this.moveSelected('down')
    },
    async moveSelected(direction: 'up' | 'down') {
      const editor = useEditorStore()
      const id = editor.selectedComponentId
      const target = moveIndex(editor.currentPayload, id, direction)
      if (!id || !target) return
      await editor.flushInline()
      const moved = await editor.executeCommand('move-component', {
        component_id: id,
        target_parent_id: target.parentId,
        index: target.index,
      })
      if (moved) editor.selectComponent(id) // preserve selection through the move
      this.closeMenu()
    },

    selectParent() {
      const editor = useEditorStore()
      const parent = editor.selectedComponent?.parentId
      if (parent) editor.selectComponent(parent)
      this.closeMenu()
    },

    async pasteInsideSelected() {
      const editor = useEditorStore()
      await editor.flushInline()
      await editor.pasteInto(editor.selectedComponentId)
      this.closeMenu()
    },

    async pasteBeforeSelected() {
      await this.pasteRelative('before')
    },
    async pasteAfterSelected() {
      await this.pasteRelative('after')
    },
    async pasteRelative(position: 'before' | 'after') {
      const editor = useEditorStore()
      const clip = editor.clipboard
      const id = editor.selectedComponentId
      if (!clip || !id) return
      const target = siblingInsert(editor.currentPayload, id, position)
      if (!target) return
      if (clip.kind === 'block' && target.parentId === null) {
        editor.commandError = 'Add a section before pasting a block here.'
        return
      }
      await editor.flushInline()
      await editor.executeCommand('insert-component', {
        component: clip.node,
        target_parent_id: target.parentId,
        index: target.index,
      })
      this.closeMenu()
    },

    // ---- Phase 26.1 — layout actions (all one undo via the command bus) ----
    // Insert an empty column into the selected container (append).
    async addColumnToSelected() {
      const editor = useEditorStore()
      const id = editor.selectedComponentId
      if (!id) return
      await editor.flushInline()
      await editor.executeCommand('insert-component', {
        component: emptyColumn('100'),
        target_parent_id: id,
        index: childrenCount(editor.currentPayload, id),
      })
      this.closeMenu()
    },

    // Insert a fresh container into the selected section (append).
    async addContainerToSelected(widths?: string[]) {
      const editor = useEditorStore()
      const id = editor.selectedComponentId
      if (!id) return
      await editor.flushInline()
      await editor.executeCommand('insert-component', {
        component: newContainer(widths),
        target_parent_id: id,
        index: childrenCount(editor.currentPayload, id),
      })
      this.closeMenu()
    },

    // Open the component picker for the selected column instead of inserting a
    // hardcoded default (Phase 26.3.8/26.3.10): "Add Element" must let the user
    // choose. The pick commits through the single guarded insertComponent()
    // entry point — the same path the Navigator "Add Inside" picker uses.
    addElementToSelected() {
      this.openAddElementPicker()
    },

    // Reverse the selected container's column order via the dedicated command —
    // a structural reorder, so it goes through the CommandBus (one undo entry),
    // not a data patch that would write the `blocks` key past normalization.
    async reverseColumnsOnSelected() {
      const editor = useEditorStore()
      const id = editor.selectedComponentId
      if (!id) return
      // Nothing to reverse below two columns — skip the round-trip.
      if (childrenCount(editor.currentPayload, id) < 2) return
      await editor.flushInline()
      await editor.reverseColumns(id)
      this.closeMenu()
    },

    // Shallow-merge a data patch onto the selection (align/justify/wrap/width/…).
    async patchSelectedData(data: Record<string, unknown>) {
      const editor = useEditorStore()
      const id = editor.selectedComponentId
      if (!id) return
      await editor.updateComponentDataById(id, data)
      this.closeMenu()
    },

    // ---- Layout preset popup ------------------------------------------
    openLayoutPopup() {
      this.addElementPickerOpen = false
      this.layoutPopupOpen = true
      this.closeMenu()
    },
    closeLayoutPopup() {
      this.layoutPopupOpen = false
    },

    // ---- Add Element component picker (Phase 26.3.10) ------------------
    // Opens the same ComponentPicker the Navigator uses, anchored to the
    // selected container's toolbar. Both entry points — the toolbar button and
    // the empty-column "+ Add Element" affordance — funnel through here.
    openAddElementPicker() {
      const editor = useEditorStore()
      const sel = editor.selectedComponent
      if (!editor.selectedComponentId) return
      this.layoutPopupOpen = false
      this.addElementPickerOpen = true
      this.closeMenu()
      // Phase 26.3.12 — DEV entrypoint traces: prove the "+ Add Element" click
      // opens the picker (never a hardcoded insert) and which candidates a column
      // offers. Gated by pbDebugEnabled() (dev build, or window.__PB_DEBUG__ = true).
      if (pbDebugEnabled()) {
        // eslint-disable-next-line no-console
        console.debug('[PB-ADD-ELEMENT-CLICK]', {
          selectedId: editor.selectedComponentId,
          selectedType: sel?.type ?? null,
          selectedKind: sel?.kind ?? null,
          handler: 'openAddElementPicker',
        })
        // eslint-disable-next-line no-console
        console.debug('[PB-COMPONENT-PICKER]', {
          parentId: editor.selectedComponentId,
          parentType: sel?.type ?? null,
          candidates: this.addElementCandidates.map((d) => d.type),
        })
      }
    },
    closeAddElementPicker() {
      this.addElementPickerOpen = false
    },
    // Commit a picked component through the single guarded insertion entry point
    // (nesting + lock + dirty-guard pre-checks, one undo). insertComponent()
    // appends to the end of the selected container and selects the new node.
    async chooseAddElement(def: DefinitionMeta) {
      const editor = useEditorStore()
      const parentId = editor.selectedComponentId
      this.addElementPickerOpen = false
      if (!parentId) return
      // Phase 26.3.12 — DEV: prove the insertion targets the SELECTED column
      // (not a section/container/last-selected). Stripped from production.
      if (pbDebugEnabled()) {
        // eslint-disable-next-line no-console
        console.debug('[PB-INSERT-CONTEXT]', { selected: parentId, targetParent: parentId, componentType: def.type })
      }
      await editor.insertComponent({ parentId, componentType: def.type })
    },

    /**
     * Apply a chosen layout preset to the current selection. For a Section this
     * INSERTS a new container pre-filled with the preset's columns (one undo). For
     * a Container it re-proportions the existing columns; for a Column it sets that
     * column's width. Always one command = one undo entry.
     */
    async applyLayoutPreset(widths: string[]) {
      const editor = useEditorStore()
      const kind = this.actionKind
      const id = editor.selectedComponentId
      this.closeLayoutPopup()
      if (!id) return
      // Commit any pending inline text edit first so it lands as its own undo
      // step rather than being clobbered by the layout command.
      await editor.flushInline()
      if (kind === 'section') {
        await this.addContainerToSelected(widths)
        return
      }
      if (kind === 'column') {
        const width = widths[0]
        if (width !== undefined) {
          await editor.updateComponentDataById(id, { width })
        }
        return
      }
      // container (or any other layout host): re-proportion columns.
      await editor.applyColumnLayout(id, widths)
    },
  },
})
