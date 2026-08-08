// Phase 26.33 — Template Distribution Provider contract (a source-of-origin abstraction).
//
// A distribution provider answers "what does this source make available?" over the EXISTING Phase
// 26.21 Template Library sources. It is a thin, read-only classification layer: browse a source's
// templates and check whether the source is currently reachable. It NEVER resolves or applies a
// layout, runs a command, touches BuilderState, or owns template storage — it adapts existing
// providers. No MarketplaceManager / CommerceService / DistributionEngine, no payments.

import type { TemplateLibraryItem } from '../library'

export interface TemplateDistributionProvider {
  /** The invariant distribution source KEY this provider represents. */
  source(): string
  /** Whether the source is currently reachable (a builtin/local source is always available). */
  available(): boolean
  /** Every template the source makes available, as immutable library metadata snapshots. */
  list(): TemplateLibraryItem[]
  /** One template by its library-wide id, or null. */
  find(id: string): TemplateLibraryItem | null
}
