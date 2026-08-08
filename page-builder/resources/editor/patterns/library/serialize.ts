// Phase 26.11 — serialize a live component tree into an id-free snapshot.
//
// It reuses the canonical child-storage contract (`utils/layout.ts`): a section's
// children are its top-level `blocks[]`; a block's (container/column) children are its
// `data.blocks[]`. Everything else in `data` — normal values, `{{token}}` references,
// responsive envelopes, and style-state maps — is copied verbatim, so all Phase
// 26.6–26.10 capabilities survive untouched.
//
// The clone is reactive-safe (`toRaw` unwraps Vue proxies; JSON strips any residual
// reactivity), matching the approach the clipboard/copy path already uses.

import { toRaw } from 'vue'
import type { BlockNode, SectionNode } from '../../types'
import type { PatternBlockSnapshot, PatternSectionSnapshot, PatternTree } from './patternDefinition'

/** A plain, JSON-safe deep clone. Safe on reactive payloads and on plain snapshots. */
function clonePlain<T>(value: T): T {
  return JSON.parse(JSON.stringify(toRaw(value ?? ({} as T)))) as T
}

/** Serialize a block, stripping its id and recursing into container children. */
function serializeBlock(block: BlockNode): PatternBlockSnapshot {
  const data = clonePlain(block.data ?? {})
  if (Array.isArray(data.blocks)) {
    data.blocks = (data.blocks as BlockNode[]).map(serializeBlock)
  }
  return { type: block.type, data }
}

/** Serialize a section, stripping its id and its blocks' ids. */
export function serializeSection(section: SectionNode): PatternSectionSnapshot {
  return {
    type: section.type,
    data: clonePlain(section.data ?? {}),
    blocks: (section.blocks ?? []).map(serializeBlock),
  }
}

/** Serialize one or more sections into a reusable, id-free pattern tree. */
export function serializePattern(sections: SectionNode[]): PatternTree {
  return { sections: sections.map(serializeSection) }
}
