// Phase 15 / 24.2 — inspector field controller.
//
// PropertyInspector owns the local edit state and provides a small controller
// through Vue's provide/inject so field components deep in the tree can read and
// write values (with dot-path + translatable handling) without prop drilling.

import type { InjectionKey } from 'vue'
import type { SchemaEntry, SchemaField } from './schema'

export interface FieldController {
  /** Resolve a field's current value from local state (translatable-aware). */
  getValue(field: SchemaField): unknown
  /** Stage a new value for a field into LOCAL state only (no backend commit). */
  setValue(field: SchemaField, value: unknown): void
  /** Commit staged edits to the backend (field blur / Enter / immediate fields). */
  commit(): void
  /** Revert a field to its last committed value (Escape). */
  revert(field: SchemaField): void
  /** Current editing locale for translatable fields. */
  locale: string

  // ---- Phase 24.2 — per-field dirty UX -------------------------------
  /** Whether this field's staged draft differs from the applied value. */
  fieldDirty(field: SchemaField): boolean
  /** Restore ONLY this field to its applied value (no command). */
  revertField(field: SchemaField): void
  /** Count of dirty leaf fields within a set of entries (tab / group badges). */
  dirtyCount(entries: SchemaEntry[]): number
}

export const FieldControllerKey: InjectionKey<FieldController> = Symbol('pb-field-controller')

// ---- Phase 24.2 — editor-local search / visibility view ---------------
// Provided alongside the controller so the field tree can hide non-matching
// fields, auto-expand matching groups, and skip empty tabs while searching. This
// never touches the schema or payload — it is purely a render filter.
export interface InspectorView {
  /** Current search query (empty = no filter). */
  query: string
  /** True while a non-empty query is active. */
  searching: boolean
  /** Whether a leaf field should render under the current query. */
  fieldVisible(field: SchemaField): boolean
  /** Whether an entry (field or group) should render under the current query. */
  entryVisible(entry: SchemaEntry): boolean
}

export const InspectorViewKey: InjectionKey<InspectorView> = Symbol('pb-inspector-view')
