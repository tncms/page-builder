// Phase 14.2 — Palette insert payload builder.
//
// Turns a palette definition (from editor metadata) into the minimal component
// payload the backend insert-component command expects. Ids are deliberately
// omitted — InsertComponentCommand regenerates them server-side (section vs
// block by target), so the client never invents ids. The shape mirrors the
// backend normalizer (LayoutMutator::shapeSection / shapeBlock): a section
// carries `blocks: []`, a block carries only `type` + `data`.
//
// The definition comes from reactive editor metadata, so its `defaults` may be a
// Vue reactive proxy. We must produce a plain, JSON-safe object: structuredClone
// throws on reactive proxies, so clone via toRaw + JSON instead. The result
// contains only JSON-safe values (no proxies, functions, refs, symbols, DOM).

import { toRaw } from 'vue'
import type { DefinitionMeta, ComponentKind } from '../types'

export interface ComponentPayload {
  type: string
  data: Record<string, unknown>
  blocks?: unknown[]
}

/** Deep-clone a definition's defaults into a plain JSON object; {} on failure. */
function plainData(value: unknown): Record<string, unknown> {
  try {
    const raw = toRaw(value ?? {})
    const cloned = JSON.parse(JSON.stringify(raw)) as unknown
    return cloned && typeof cloned === 'object' && !Array.isArray(cloned)
      ? (cloned as Record<string, unknown>)
      : {}
  } catch (error) {
    if (import.meta.env.DEV) {
      // eslint-disable-next-line no-console
      console.warn('[page-builder] createComponentPayload: defaults not JSON-cloneable, using {}', error)
    }
    return {}
  }
}

export function createComponentPayload(definition: DefinitionMeta, kind: ComponentKind): ComponentPayload {
  const payload: ComponentPayload = { type: definition.type, data: plainData(definition.defaults) }
  if (kind === 'section') payload.blocks = []
  return payload
}
