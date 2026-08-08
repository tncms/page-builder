// Phase 26.30 — Template Pack Installation Foundation barrel.
//
// Adoption layer between Phase 26.28 starter packs and the existing Phase 26.24 template sources.
// Installing a pack validates availability and records a REFERENCE (never a copy) in the installed
// ledger. It makes templates available; it does not place content in the builder, run commands, or
// touch BuilderState — no runtime coupling. Idempotent; missing pack / unavailable template →
// rejected. No marketplace / purchase / payment / licensing. See docs/template-pack-installation.md.

export * from './installationTypes'
export { InstalledPackRegistry, installedPackRegistry } from './installedPackRegistry'
export { StarterPackInstaller, starterPackInstaller, type StarterPackInstallerDeps } from './starterPackInstaller'
