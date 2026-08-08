// Phase 26.13 — Pattern authoring store (editor-local).
//
// Drives the "Save as Pattern" flow: read the current Builder selection, validate
// metadata, serialize via Phase 26.11, register in the library, and surface non-blocking
// feedback. Saving is METADATA CREATION ONLY — it never dispatches a command, mutates the
// component tree, or creates an undo entry. It reuses the existing selection system (no
// new selection state, mode, or traversal engine).

import { defineStore } from 'pinia'
import { useEditorStore } from '../../stores/editor'
import { findComponentNode } from '../../utils/layout'
import { patternProvider } from '../provider'
import { usePatternPickerStore } from '../picker/patternPickerStore'
import { usePatternRuntimeStore } from '../runtime/PatternRuntimeStore'
import { buildPatternDefinition } from './savePattern'
import { validatePatternMetadata, isSupportedRoot, type PatternMetadataErrors } from './patternValidation'
import type { ComponentKind } from '../../types'

interface State {
  open: boolean
  name: string
  category: string
  description: string
  sourceId: string | null
  sourceKind: ComponentKind | null
  errors: PatternMetadataErrors & { root?: string }
  feedback: string | null
}

export const usePatternAuthoringStore = defineStore('pbPatternAuthoring', {
  state: (): State => ({
    open: false,
    name: '',
    category: 'layout',
    description: '',
    sourceId: null,
    sourceKind: null,
    errors: {},
    feedback: null,
  }),

  getters: {
    /** Whether the current selection is a saveable layout root (drives the toolbar action). */
    canSaveSelection(): boolean {
      const editor = useEditorStore()
      const hit = findComponentNode(editor.currentPayload, editor.selectedComponentId)
      return !!hit && isSupportedRoot(hit.node.type, hit.kind)
    },
  },

  actions: {
    /** Open the save dialog for the current selection; refuses an unsupported root. */
    openDialog(): boolean {
      const editor = useEditorStore()
      const hit = findComponentNode(editor.currentPayload, editor.selectedComponentId)
      if (!hit || !isSupportedRoot(hit.node.type, hit.kind)) {
        this.errors = { root: 'Only sections, containers, or columns can be saved as patterns.' }
        return false
      }
      this.sourceId = editor.selectedComponentId
      this.sourceKind = hit.kind
      this.name = ''
      this.category = 'layout'
      this.description = ''
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

    close(): void {
      this.open = false
      this.errors = {}
    },
    clearFeedback(): void {
      this.feedback = null
    },

    /**
     * Validate + create the pattern. Registers a NEW definition (fresh unique id, so an
     * existing pattern is never overwritten) and refreshes the picker so it appears
     * immediately. Returns false (with errors set) on invalid input. No command, no undo.
     */
    save(): boolean {
      const meta = { name: this.name, category: this.category, description: this.description }
      const errors = validatePatternMetadata(meta)
      this.errors = errors
      if (Object.keys(errors).length > 0) return false

      const editor = useEditorStore()
      const hit = findComponentNode(editor.currentPayload, this.sourceId)
      if (!hit || !isSupportedRoot(hit.node.type, hit.kind)) {
        this.errors = { root: 'The selected component can no longer be saved.' }
        return false
      }

      const definition = buildPatternDefinition({ node: hit.node, kind: hit.kind, ...meta })
      patternProvider.register(definition)
      usePatternRuntimeStore().trackCreated(definition.id) // runtime-only "recently created"
      usePatternPickerStore().load() // keep an open picker in sync — appears immediately

      this.feedback = `Pattern "${definition.name}" created.`
      this.open = false
      return true
    },
  },
})
