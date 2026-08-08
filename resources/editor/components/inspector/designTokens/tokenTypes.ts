// Phase 26.7 — Design token model (frontend mirror).
//
// Tokens are authored on the PHP side (`src/Design/*`) and shipped to the editor in
// `metadata.tokens` as a `{ group: { name: value } }` map. The frontend NEVER hardcodes
// the token list — it reflects whatever the metadata carries, so a theme/plugin that
// registers extra tokens surfaces them with no editor change.

/** A single resolved token flattened out of the metadata map. */
export interface DesignToken {
  /** Group id, e.g. `color`, `spacing`, `typography`. */
  group: string
  /** Name within the group, e.g. `primary`, `lg`. */
  name: string
  /** Dotted key used in references, e.g. `color.primary`. */
  key: string
  /** The concrete value — a string, or an object for composite tokens (typography). */
  value: unknown
  /** Human label derived from the name (metadata carries no label). */
  label: string
}

/** Known token group ids — used for stable ordering + labels in the picker. */
export type TokenType = 'color' | 'spacing' | 'radius' | 'shadow' | 'typography' | 'container' | 'breakpoint'

interface TokenGroupMeta {
  label: string
  order: number
}

/**
 * Display metadata for the built-in groups. Unknown groups (plugin/theme tokens) are
 * still listed — they just sort after the known ones with a humanized label.
 */
export const TOKEN_GROUP_META: Record<string, TokenGroupMeta> = {
  color: { label: 'Colors', order: 0 },
  spacing: { label: 'Spacing', order: 1 },
  radius: { label: 'Radius', order: 2 },
  shadow: { label: 'Shadow', order: 3 },
  typography: { label: 'Typography', order: 4 },
  container: { label: 'Container', order: 5 },
  breakpoint: { label: 'Breakpoint', order: 6 },
}

/** Sort order for a group id (known groups first, then alphabetical). */
export function tokenGroupOrder(group: string): number {
  return TOKEN_GROUP_META[group]?.order ?? 100
}

/** A display label for a group id. */
export function tokenGroupLabel(group: string): string {
  return TOKEN_GROUP_META[group]?.label ?? humanize(group)
}

/** Turn a token name into a readable label: `off-white` → `Off White`, `2xl` → `2xl`. */
export function humanize(name: string): string {
  return name
    .replace(/[-_]+/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())
    .trim()
}
