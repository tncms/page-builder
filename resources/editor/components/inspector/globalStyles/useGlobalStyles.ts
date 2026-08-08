// Phase 26.8 — inspector integration seam.
//
// The one place the Global Style layer is tied to the rest of the property system:
// it resolves against the app-wide registry using the live token registry (26.7) and
// the active breakpoint (26.6). This is a SEAM, not wired UI — a future phase can call
// `resolve()` from an inspector field to show an "inherited from Global" value/badge or
// to preview a global style, with no change to how editors commit today.

import { activeBreakpoint } from '../responsive/activeBreakpoint'
import { useTokens } from '../designTokens/useTokens'
import { globalStyleRegistry } from './registry'
import { resolveStyle } from './resolver'
import type { StyleQuery, StyleResolution } from './types'

export interface UseGlobalStyles {
  /** The app-wide registry (register/override/inspect rules). */
  registry: typeof globalStyleRegistry
  /** Resolve a property through the priority chain, using live tokens + breakpoint. */
  resolve(query: StyleQuery): StyleResolution
}

/** Bind the Global Style resolver to the current editor context. */
export function useGlobalStyles(): UseGlobalStyles {
  const tokens = useTokens()
  return {
    registry: globalStyleRegistry,
    resolve: (query: StyleQuery): StyleResolution =>
      resolveStyle(query, globalStyleRegistry, { tokens: tokens.value, breakpoint: activeBreakpoint.value }),
  }
}
