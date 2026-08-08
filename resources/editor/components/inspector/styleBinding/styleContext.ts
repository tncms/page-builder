// Phase 26.9 — the style context provided to adopted editors.
//
// PropertyInspector (which knows the selected component + owns the store) provides this
// so the StyleBoundField wrapper can resolve tokens/styles WITHOUT coupling the pure
// editors — or the wrapper itself — to the store. When the context is absent (e.g. a
// component mounted in isolation in a test), the wrapper degrades to a transparent
// pass-through, which is what keeps existing component tests green.

import type { InjectionKey } from 'vue'
import type { TokenRegistry } from '../designTokens/TokenRegistry'
import type { StyleResolution } from '../globalStyles/types'
import type { ComponentStyleQuery } from './resolveComponentStyleValue'

export interface StyleContext {
  /** The live token registry (from metadata). */
  tokens: () => TokenRegistry
  /** The active editing breakpoint id. */
  breakpoint: () => string
  /** The selected component's type. */
  componentType: () => string
  /** The selected component's instance id. */
  componentId: () => string | undefined
  /** Resolve a property through the priority chain (the single entry point). */
  resolve: (query: ComponentStyleQuery) => StyleResolution
}

export const StyleContextKey: InjectionKey<StyleContext> = Symbol('pb-style-context')
