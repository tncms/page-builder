// Phase 26.17 — Template provider barrel.
//
// The runtime imports `templateProvider` from here (never the concrete class). To add a
// future persistent provider, implement the Phase 26.16 `TemplateProvider` contract and
// change this one binding — no runtime edits required.

export { LocalTemplateProvider, localTemplateProvider } from './LocalTemplateProvider'

/** The active template provider. Swap here to change the source of truth. */
export { localTemplateProvider as templateProvider } from './LocalTemplateProvider'
