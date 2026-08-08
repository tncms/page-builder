// Phase 26.11 — Pattern Library Foundation barrel.
//
// The AUTHORING side of patterns: capture a component tree, register it, and replay it
// through the existing insert pipeline. Distinct from the server-manifest CONSUMPTION
// store (`../patternStore.ts`, `../types.ts`). No renderer/core/CommandBus/migration
// changes, no marketplace. See docs/pattern-library.md.

export * from './patternDefinition'
export * from './serialize'
export * from './restore'
export * from './registry'
export * from './insert'
export { uniqueId } from './ids'
