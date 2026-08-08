// Phase 26.33 — the active distribution providers + the default registry.
//
// One library-backed provider per ACTIVE source, adapting the existing Phase 26.21 library
// instances (no duplicate storage). The `local` provider deliberately adapts the library `user`
// source. The default registry registers them in a FIXED order (builtin → local → imported) so
// discovery is deterministic. Registering a new channel (e.g. a future marketplace) is the only
// change a new source needs — the contract and registry are untouched.

import { builtinTemplateLibrary, userTemplateLibrary, importedTemplateLibrary } from '../library'
import { DISTRIBUTION_SOURCE } from './distributionSource'
import { LibraryBackedDistributionProvider } from './LibraryBackedDistributionProvider'
import { TemplateDistributionRegistry } from './TemplateDistributionRegistry'

/** Templates shipped with the CMS / a theme. */
export const builtinDistributionProvider = new LibraryBackedDistributionProvider(
  DISTRIBUTION_SOURCE.builtin,
  builtinTemplateLibrary,
)

/** The user's own saved templates (adapts the library `user` source). */
export const localDistributionProvider = new LibraryBackedDistributionProvider(
  DISTRIBUTION_SOURCE.local,
  userTemplateLibrary,
)

/** Templates brought in via the Phase 26.26 package import. */
export const importedDistributionProvider = new LibraryBackedDistributionProvider(
  DISTRIBUTION_SOURCE.imported,
  importedTemplateLibrary,
)

/** The active distribution registry. Deterministic order: builtin → local → imported. */
export const templateDistribution = new TemplateDistributionRegistry()
templateDistribution.register(builtinDistributionProvider)
templateDistribution.register(localDistributionProvider)
templateDistribution.register(importedDistributionProvider)
