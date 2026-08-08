// Phase 26.33 — Template Distribution Provider Foundation barrel.
//
// A source-of-origin abstraction OVER the existing Phase 26.21 Template Library sources: each
// distribution provider adapts one library source (builtin/user→local/imported) so templates can be
// browsed by where they are distributed from. Read-only and additive: it duplicates no storage,
// resolves/applies nothing, runs no command, and never touches BuilderState — the library, Picker,
// runtime, and Builder are unchanged. Deterministic ordering; reserved marketplace/remote/cloud
// keys but NO marketplace/product/purchase/payment/licensing. See docs/template-distribution-provider.md.

export * from './distributionSource'
export type { TemplateDistributionProvider } from './TemplateDistributionProvider'
export { LibraryBackedDistributionProvider } from './LibraryBackedDistributionProvider'
export { TemplateDistributionRegistry, DistributionRegistryError } from './TemplateDistributionRegistry'
export {
  templateDistribution,
  builtinDistributionProvider,
  localDistributionProvider,
  importedDistributionProvider,
} from './templateDistribution'
