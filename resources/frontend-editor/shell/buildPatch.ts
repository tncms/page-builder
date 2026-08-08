// Phase 17 — Translate a pre-commit inspector edit (LivePatch) into the granular
// pb:patch-* messages the iframe applies. Pure: consults the shared patch map and
// resolves design-token colour references from editor metadata. Returns [] when
// the field isn't live-patchable (the commit-time full render handles it).

import { resolvePatchTarget } from '../iframe/patchMap'
import type { EditorMetadata, LivePatch } from '../../editor/types'

export interface PatchMessage {
  type: 'pb:patch-style' | 'pb:patch-text' | 'pb:patch-attr'
  payload: Record<string, unknown>
}

export function buildPatchMessages(patch: LivePatch, metadata: EditorMetadata | null): PatchMessage[] {
  if (!patch.componentId) return []

  const target = resolvePatchTarget(patch.type, patch.field)
  if (!target) return []

  const componentId = patch.componentId
  const selector = target.selector

  if (target.kind === 'text') {
    return [{ type: 'pb:patch-text', payload: { componentId, selector, text: toText(patch.value) } }]
  }

  if (target.kind === 'attr') {
    if (!target.attr) return []
    return [
      { type: 'pb:patch-attr', payload: { componentId, selector, attr: target.attr, value: toText(patch.value) } },
    ]
  }

  // style
  if (!target.styleProp) return []
  const value = target.token ? resolveToken(toText(patch.value), metadata) : toText(patch.value)
  return [{ type: 'pb:patch-style', payload: { componentId, selector, style: { [target.styleProp]: value } } }]
}

function toText(value: unknown): string {
  return value == null ? '' : String(value)
}

/**
 * Resolve a "group.name" design-token reference to its concrete value using the
 * editor metadata token table, so a live colour patch shows the real colour.
 * Non-token values (hex, keyword) pass through unchanged.
 */
function resolveToken(value: string, metadata: EditorMetadata | null): string {
  const match = /^([a-z0-9_-]+)\.([a-z0-9_-]+)$/i.exec(value)
  if (!match || !metadata?.tokens) return value

  const group = metadata.tokens[match[1]]
  const token = group?.[match[2]]
  if (token == null) return value

  if (typeof token === 'object' && 'value' in (token as Record<string, unknown>)) {
    const inner = (token as Record<string, unknown>).value
    return typeof inner === 'string' ? inner : value
  }

  return typeof token === 'string' ? token : value
}
