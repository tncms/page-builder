// Phase 26.7 — reactive token registry from the editor metadata.
//
// A thin composable so any editor/component gets a `TokenRegistry` built from the
// live `metadata.tokens`. Rebuilds when the metadata changes; read-only.

import { computed, type ComputedRef } from 'vue'
import { useEditorStore } from '../../../stores/editor'
import { TokenRegistry } from './TokenRegistry'

/** The token registry for the current editor metadata. */
export function useTokens(): ComputedRef<TokenRegistry> {
  const store = useEditorStore()
  return computed(() => new TokenRegistry((store.metadata?.tokens ?? {}) as Record<string, Record<string, unknown>>))
}
