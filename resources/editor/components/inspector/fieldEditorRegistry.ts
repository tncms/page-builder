// Phase 26.4 — Property Editor Registry.
//
// The single source of truth mapping a schema field `type` to the Vue editor that
// renders it. Extracted from the inline map that used to live in InspectorField so
// editors are registered in ONE place, never duplicated, and third parties (future
// plugins/themes) can add an editor with a single `registerFieldEditor` call.
//
// This module is intentionally free of any `.vue` import: the concrete editors are
// registered by `registerBuiltinFieldEditors.ts` (a side-effect module) so this
// registry can be imported from an editor component (e.g. ResponsiveField resolving
// its inner editor) without an import cycle.

import type { Component } from 'vue'

export interface FieldEditorDescriptor {
  /** The Vue component that renders this field type. */
  component: Component
  /**
   * Pass the full SchemaField through as a `field` prop. Needed by editors that
   * read schema metadata beyond the value — options (select), min/max (number),
   * or the wrapped inner field (responsive).
   */
  needsField?: boolean
}

const registry = new Map<string, FieldEditorDescriptor>()

/**
 * Register (or override) the editor for a field `type`. Overriding is allowed so a
 * theme/plugin can replace a built-in; in dev we warn so an accidental collision is
 * visible.
 */
export function registerFieldEditor(type: string, descriptor: FieldEditorDescriptor): void {
  if (import.meta.env?.DEV && registry.has(type)) {
    // eslint-disable-next-line no-console
    console.warn(`[page-builder] field editor "${type}" is being overridden.`)
  }
  registry.set(type, descriptor)
}

/** The editor descriptor for a field `type`, or null when none is registered. */
export function resolveFieldEditor(type: string): FieldEditorDescriptor | null {
  return registry.get(type) ?? null
}

/** Whether an editor is registered for a field `type`. */
export function isFieldEditorRegistered(type: string): boolean {
  return registry.has(type)
}

/** Every registered field type (for diagnostics/tests). */
export function registeredFieldTypes(): string[] {
  return [...registry.keys()]
}
