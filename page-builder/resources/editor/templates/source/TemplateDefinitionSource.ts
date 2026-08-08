// Phase 26.24 — Template definition resolution contracts.
//
// The Library (Phase 26.21) is metadata-only: a `TemplateLibraryItem` has no layout tree. To
// APPLY a template the runtime needs the full `TemplateDefinition`. This layer supplies it,
// keyed by (templateId, source), WITHOUT the Library or the runtime holding definition storage.
//
// Two contracts:
//   • `TemplateDefinitionSource`   — one source's definitions (builtin / user / imported).
//   • `TemplateDefinitionProvider` — resolves across sources by dispatching on the source KEY.
//
// Resolution is discovery-adjacent, NOT apply: sources never resolve patterns, run commands, or
// touch BuilderState. A miss is an explicit `null` (never a fabricated template, never a silent
// fallback to another source).

import type { TemplateDefinition } from '../contracts'

/** One source's definition store. `source` is the invariant KEY it answers for. */
export interface TemplateDefinitionSource {
  readonly source: string
  /** The full definition for a templateId within this source, or null when absent. */
  getDefinition(templateId: string): TemplateDefinition | null
}

/** Resolves a definition across sources. Unknown source KEYS fail explicitly (never null-as-unknown). */
export interface TemplateDefinitionProvider {
  getDefinition(templateId: string, source: string): TemplateDefinition | null
}
