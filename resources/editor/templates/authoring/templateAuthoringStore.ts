// Phase 26.18 — template authoring store (editor-local).
//
// Drives the "Save as Template" flow: capture the current layout (a selected section /
// container, OR the whole page), validate metadata, serialize via Phase 26.11, and register
// through the Phase 26.16/26.17 `TemplateProvider`. Saving is METADATA CREATION ONLY — it
// never dispatches a command, mutates the component tree, or creates an undo entry. It
// reuses the existing selection system (no new selection state, mode, or traversal engine)
// and the existing Template Runtime for read-back (nothing new inserted here).

import { defineStore } from 'pinia'
import { useEditorStore } from '../../stores/editor'
import { findComponentNode } from '../../utils/layout'
import { localTemplateProvider } from '../provider'
import { useAssetPickerStore } from '../../assets/assetPickerStore'
import { buildTemplateDefinition, wrapSelectionAsSections } from './captureTemplate'
import { validateTemplateMetadata, isSupportedTemplateRoot, type TemplateMetadataErrors } from './templateValidation'
import type { ComponentKind, SectionNode } from '../../types'

/** Where a template's layout is captured from. */
export type TemplateCaptureScope = 'selection' | 'page'

interface State {
  open: boolean
  scope: TemplateCaptureScope
  name: string
  category: string
  description: string
  /** Optional thumbnail URL (stored on the template's `preview`; URL only, never a media id). */
  previewUrl: string
  sourceId: string | null
  sourceKind: ComponentKind | null
  errors: TemplateMetadataErrors & { root?: string }
  feedback: string | null
}

export const useTemplateAuthoringStore = defineStore('pbTemplateAuthoring', {
  state: (): State => ({
    open: false,
    scope: 'selection',
    name: '',
    category: 'page',
    description: '',
    previewUrl: '',
    sourceId: null,
    sourceKind: null,
    errors: {},
    feedback: null,
  }),

  getters: {
    /** Whether the current selection is a saveable template root (section or container). */
    canSaveSelection(): boolean {
      const editor = useEditorStore()
      const hit = findComponentNode(editor.currentPayload, editor.selectedComponentId)
      return !!hit && isSupportedTemplateRoot(hit.node.type, hit.kind)
    },
    /** Whether the page has at least one section to capture as a full-page template. */
    canSavePage(): boolean {
      const editor = useEditorStore()
      return (editor.currentPayload?.sections?.length ?? 0) > 0
    },
  },

  actions: {
    /**
     * Open the save dialog for the given scope. 'selection' refuses an unsupported root;
     * 'page' refuses an empty page. Metadata is reset each time.
     */
    openDialog(scope: TemplateCaptureScope = 'selection'): boolean {
      const editor = useEditorStore()

      if (scope === 'selection') {
        const hit = findComponentNode(editor.currentPayload, editor.selectedComponentId)
        if (!hit || !isSupportedTemplateRoot(hit.node.type, hit.kind)) {
          this.errors = { root: 'Only a section or container selection can be saved as a template.' }
          return false
        }
        this.sourceId = editor.selectedComponentId
        this.sourceKind = hit.kind
      } else {
        if (!this.canSavePage) {
          this.errors = { root: 'The page has no sections to save as a template.' }
          return false
        }
        this.sourceId = null
        this.sourceKind = null
      }

      this.scope = scope
      this.name = ''
      this.category = 'page'
      this.description = ''
      this.previewUrl = ''
      this.errors = {}
      this.open = true
      return true
    },

    setName(value: string): void {
      this.name = value
    },
    setCategory(value: string): void {
      this.category = value
    },
    setDescription(value: string): void {
      this.description = value
    },

    setPreviewUrl(value: string): void {
      this.previewUrl = value
    },
    /** Pick a thumbnail through the SHARED Asset Source Picker; stores the URL only. */
    async chooseThumbnail(): Promise<void> {
      const result = await useAssetPickerStore().openPicker({ accept: ['image/*'], current: this.previewUrl })
      if (result) this.previewUrl = result.src
    },
    clearThumbnail(): void {
      this.previewUrl = ''
    },

    close(): void {
      this.open = false
      this.errors = {}
    },
    clearFeedback(): void {
      this.feedback = null
    },

    /**
     * Re-read the captured layout at save time (the tree may have changed since the dialog
     * opened). Returns section-rooted nodes to serialize, or null with `errors.root` set.
     */
    captureSections(): SectionNode[] | null {
      const editor = useEditorStore()

      if (this.scope === 'page') {
        const sections = editor.currentPayload?.sections ?? []
        if (sections.length === 0) {
          this.errors = { root: 'The page has no sections to save as a template.' }
          return null
        }
        return sections
      }

      const hit = findComponentNode(editor.currentPayload, this.sourceId)
      if (!hit || !isSupportedTemplateRoot(hit.node.type, hit.kind)) {
        this.errors = { root: 'The selected component can no longer be saved.' }
        return null
      }
      return wrapSelectionAsSections(hit.node, hit.kind)
    },

    /**
     * Validate + create the template. Serializes the captured layout, builds a NEW
     * `TemplateDefinition` (fresh unique id — never overwrites an existing template) and
     * registers it through the `TemplateProvider`. Returns false (with errors set) on invalid
     * input or a lost selection. No command, no undo, no tree mutation.
     */
    save(): boolean {
      const meta = { name: this.name, category: this.category, description: this.description }
      const errors = validateTemplateMetadata(meta)
      this.errors = errors
      if (Object.keys(errors).length > 0) return false

      const sections = this.captureSections()
      if (!sections) return false

      const definition = buildTemplateDefinition({ sections, ...meta, preview: this.previewUrl })
      localTemplateProvider.register(definition)

      this.feedback = `Template "${definition.name}" created.`
      this.open = false
      return true
    },
  },
})
