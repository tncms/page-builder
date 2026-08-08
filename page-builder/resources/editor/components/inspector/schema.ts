// Phase 15 — schema shape (mirrors the Phase 6 Schema::toArray output).

export interface SchemaField {
  name: string
  type: string
  label?: string
  default?: unknown
  required?: boolean
  /** Select options: a { value: label } map or [{ value, label }] list. */
  options?: Record<string, string> | Array<{ value: string; label: string }> | null
  responsive?: boolean
  translatable?: boolean
  help?: string | null
  rules?: unknown[]
  editor?: Record<string, unknown>
  /**
   * The wrapped base field for a `responsive` field (mirrors ResponsiveField's
   * `field` in Schema::toArray). The responsive editor renders this inner field's
   * editor; per-breakpoint values are a future capability layered on the same path.
   */
  field?: SchemaField
}

export interface SchemaGroup {
  type: 'group'
  name: string
  label?: string
  fields: SchemaEntry[]
}

export type SchemaEntry = SchemaField | SchemaGroup

export interface SchemaTab {
  name: string
  label?: string
  icon?: string | null
  fields: SchemaEntry[]
}

export interface ComponentSchema {
  tabs: SchemaTab[]
}

export function isGroup(entry: SchemaEntry): entry is SchemaGroup {
  return (entry as SchemaGroup).type === 'group' && Array.isArray((entry as SchemaGroup).fields)
}

/** Normalize select options (map or list) into a [{value,label}] list. */
export function normalizeOptions(options: SchemaField['options']): Array<{ value: string; label: string }> {
  if (!options) return []
  if (Array.isArray(options)) return options
  return Object.entries(options).map(([value, label]) => ({ value, label: String(label) }))
}
