// Phase 26.15 — Pattern provider barrel.
//
// The Pattern UI imports `patternProvider` from here (never the registry directly). To
// add a future provider, change this one binding — no UI edits required.

export type { PatternProvider } from './PatternProvider'
export { LocalPatternProvider, localPatternProvider } from './LocalPatternProvider'

/** The active provider the Pattern UI consumes. Swap here to change the source of truth. */
export { localPatternProvider as patternProvider } from './LocalPatternProvider'
