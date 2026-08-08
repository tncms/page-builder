// Phase 26.31 — Installed Template Discovery & Adoption barrel.
//
// An adoption-aware discovery layer between the Phase 26.30 installed pack ledger and the existing
// Phase 26.21 Template Library (the discovery foundation). It projects what a site has adopted and
// joins it to library metadata so installed templates are discoverable with pack provenance and an
// availability indicator. Reference-only and read-only: it duplicates no template definition,
// applies nothing, runs no command, and never touches BuilderState or the runtime. The Template
// Library, Picker, runtime, and Builder are unchanged. No pack execution / marketplace / purchase /
// licensing / cloud. See docs/installed-template-discovery.md.

export * from './installedTemplateItem'
export * from './adoptionTemplateItem'
export * from './adoptionFilters'
export { AdoptionDiscoveryProvider, adoptionDiscovery } from './AdoptionDiscoveryProvider'
export { useAdoptionDiscoveryStore } from './adoptionDiscoveryStore'
