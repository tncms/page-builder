// Phase 24.2 — reset-to-defaults resolution (pure).
//
// "Reset" stages the component's editable data back to its DEFINITION defaults
// (or the actively-applied PRESET's defaults), while preserving everything the
// schema does not own — nested blocks live outside `data`, and any internal /
// non-schema keys inside `data` are kept untouched. Reset never commits: it only
// produces the default data object the inspector stages as a dirty draft.
//
// Resolution priority for each editable field (§2):
//   1. Active preset value (when a preset is explicitly applied and still exists)
//   2. Field's schema `default`
//   3. Type-derived empty value

import { clonePlain, getByPath, setByPath } from '../../utils/dataPath'
import { isGroup, type ComponentSchema, type SchemaEntry, type SchemaField } from './schema'

/** All editable leaf-field paths declared by the schema (groups are traversed). */
export function editableFieldPaths(schema: ComponentSchema | undefined): string[] {
  const paths: string[] = []
  const walk = (entries?: SchemaEntry[]): void => {
    for (const entry of entries ?? []) {
      if (isGroup(entry)) walk(entry.fields)
      else if (entry.name) paths.push(entry.name)
    }
  }
  for (const tab of schema?.tabs ?? []) walk(tab.fields)
  return paths
}

/** Every editable leaf field, keyed by path. */
export function editableFields(schema: ComponentSchema | undefined): Map<string, SchemaField> {
  const map = new Map<string, SchemaField>()
  const walk = (entries?: SchemaEntry[]): void => {
    for (const entry of entries ?? []) {
      if (isGroup(entry)) walk(entry.fields)
      else if (entry.name) map.set(entry.name, entry)
    }
  }
  for (const tab of schema?.tabs ?? []) walk(tab.fields)
  return map
}

/** A blank value appropriate to a field type when it declares no default. */
export function emptyForType(type: string): unknown {
  switch (type) {
    case 'toggle':
      return false
    case 'number':
      return null
    case 'spacing':
    case 'typography':
      return {}
    default:
      return ''
  }
}

/**
 * The default (non-translatable) value for one field, following the §2 priority.
 * `presetData` is the active preset's data map (or undefined).
 */
export function fieldDefault(
  field: SchemaField,
  presetData?: Record<string, unknown>,
): unknown {
  if (presetData) {
    const preset = getByPath(presetData, field.name)
    if (preset !== undefined) return clonePlain(preset)
  }
  if (field.default !== undefined) return clonePlain(field.default)
  return emptyForType(field.type)
}

/**
 * Data keys that are NOT owned by the schema (internal / structural values inside
 * `data`) — these must survive a reset. Nested blocks live outside `data`, so they
 * are never part of this object and are inherently preserved.
 */
export function preserveStructuralData(
  currentData: Record<string, unknown>,
  schema: ComponentSchema | undefined,
): Record<string, unknown> {
  const owned = new Set(editableFieldPaths(schema).map((p) => p.split('.')[0]))
  const kept: Record<string, unknown> = {}
  for (const key of Object.keys(currentData ?? {})) {
    if (!owned.has(key)) kept[key] = clonePlain(currentData[key])
  }
  return kept
}

/**
 * Build the default data object for a reset: start from the current data (so
 * non-schema keys + other-locale content survive), then overwrite each editable
 * field with its resolved default. Translatable fields only reset the active locale.
 */
export function buildDefaultData(options: {
  schema: ComponentSchema | undefined
  currentData: Record<string, unknown>
  locale: string
  presetData?: Record<string, unknown>
}): Record<string, unknown> {
  const { schema, currentData, locale, presetData } = options
  let result = clonePlain(currentData ?? {}) as Record<string, unknown>
  for (const [path, field] of editableFields(schema)) {
    const def = fieldDefault(field, presetData)
    if (field.translatable) {
      const existing = getByPath(result, path)
      const base = existing && typeof existing === 'object' && !Array.isArray(existing) ? existing : {}
      result = setByPath(result, path, { ...(base as Record<string, unknown>), [locale]: def })
    } else {
      result = setByPath(result, path, def)
    }
  }
  return result
}

/** Editable field paths whose value differs between two data objects. */
export function diffFieldPaths(
  applied: Record<string, unknown>,
  draft: Record<string, unknown>,
  schema: ComponentSchema | undefined,
): string[] {
  return editableFieldPaths(schema).filter(
    (path) => JSON.stringify(getByPath(applied, path)) !== JSON.stringify(getByPath(draft, path)),
  )
}

/** Whether the applied data has any non-empty editable value (drives reset confirm). */
export function hasMeaningfulValues(
  applied: Record<string, unknown>,
  schema: ComponentSchema | undefined,
  locale: string,
): boolean {
  for (const [path, field] of editableFields(schema)) {
    let value = getByPath(applied, path)
    if (field.translatable && value && typeof value === 'object' && !Array.isArray(value)) {
      value = (value as Record<string, unknown>)[locale]
    }
    if (value !== undefined && value !== null && value !== '' && value !== false) {
      if (typeof value === 'object' && !Array.isArray(value) && Object.keys(value).length === 0) continue
      return true
    }
  }
  return false
}
