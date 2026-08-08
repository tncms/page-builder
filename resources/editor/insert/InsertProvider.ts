// Phase 26.37 — the InsertProvider contract (a router entry, NOT an inserter).
//
// A provider describes one insert category and knows how to OPEN its existing experience — nothing
// more. `open()` launches the already-built Picker/flow (Pattern Picker, Template Picker, Asset
// Picker, component palette); it never resolves items, inserts a tree, dispatches a command, or
// touches BuilderState. The provider owns no registry of its own items — those live in their
// existing sources. No MarketplaceManager / CommerceService / generation. Keep it tiny.

export interface InsertProvider {
  /** Invariant category KEY (see INSERT_CATEGORY). */
  key: string
  /** Localized display label for the category. */
  label: string
  /** Optional one-line description for the launcher. */
  description?: string
  /** Open the existing experience for this category. The provider's ONLY capability. */
  open(): void
}
