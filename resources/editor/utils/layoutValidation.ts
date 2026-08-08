import type { BlockNode, EditorMetadata, LayoutPayload, SectionNode } from '../types'
import { canContain } from './nestingPolicy'

/**
 * Layout integrity validation (Phase 26.3, Increment 7).
 *
 * A pure, deterministic structural audit of a layout payload — the last gate
 * before a draft/publish persists. The server normalizer drops malformed nodes
 * but does NOT catch duplicate ids, orphaned/blank ids, corrupted children
 * arrays, cross-container nesting violations, or unknown component types, and
 * publish copies the draft verbatim. A drag pipeline that can reshape the tree
 * makes those failure modes reachable, so the editor refuses to persist a broken
 * tree rather than shipping corruption downstream.
 *
 * The server stays authoritative on mutations; this is a client pre-save
 * safeguard, not a replacement. It reads only the payload (+ optional metadata
 * registry) and returns a flat issue list — no throwing, no mutation — so it is
 * trivially unit-testable and safe to call on every save.
 */

export interface LayoutIssue {
  /** Machine code: duplicate-id | missing-id | missing-type | unknown-type | invalid-nesting | corrupt-children | corrupt-node | corrupt-root. */
  code: string
  /** The offending component id when known, else null. */
  id: string | null
  /** Human-readable, surfaced to the user when a save is blocked. */
  message: string
}

/**
 * Audit a layout payload. Returns [] when structurally sound. When `metadata` is
 * provided (and non-empty), component types are additionally checked against the
 * registered section/block definitions; without it, type existence is skipped
 * (permissive) so the validator is usable before metadata loads.
 */
export function validateLayout(
  payload: LayoutPayload | null | undefined,
  metadata?: Pick<EditorMetadata, 'sections' | 'blocks'> | null,
): LayoutIssue[] {
  const issues: LayoutIssue[] = []
  const seen = new Set<string>()
  const sectionTypes = metadata ? new Set((metadata.sections ?? []).map((d) => d.type)) : null
  const blockTypes = metadata ? new Set((metadata.blocks ?? []).map((d) => d.type)) : null

  // Validate + register an id; returns the id when valid, else null.
  const checkId = (rawId: unknown, rawType: unknown): string | null => {
    if (typeof rawId !== 'string' || rawId.trim() === '') {
      const label = typeof rawType === 'string' && rawType.trim() !== '' ? rawType : 'component'
      issues.push({ code: 'missing-id', id: null, message: `A ${label} is missing an id.` })
      return null
    }
    if (seen.has(rawId)) {
      issues.push({ code: 'duplicate-id', id: rawId, message: `Duplicate component id "${rawId}".` })
      return rawId
    }
    seen.add(rawId)
    return rawId
  }

  // Validate a type against its registry; returns the type when present, else null.
  const checkType = (id: string | null, rawType: unknown, registry: Set<string> | null): string | null => {
    if (typeof rawType !== 'string' || rawType.trim() === '') {
      issues.push({ code: 'missing-type', id, message: `Component ${id ?? '(no id)'} is missing a type.` })
      return null
    }
    if (registry && registry.size > 0 && !registry.has(rawType)) {
      issues.push({ code: 'unknown-type', id, message: `Component ${id ?? rawType} has an unknown type "${rawType}".` })
    }
    return rawType
  }

  // Walk a children list under a known parent type, enforcing nesting + shape.
  const walkBlocks = (blocks: unknown, parentType: string): void => {
    if (!Array.isArray(blocks)) {
      issues.push({ code: 'corrupt-children', id: null, message: `A ${parentType} has a corrupted children array.` })
      return
    }
    for (const raw of blocks as BlockNode[]) {
      if (!raw || typeof raw !== 'object') {
        issues.push({ code: 'corrupt-node', id: null, message: 'A block is not a valid object.' })
        continue
      }
      const id = checkId(raw.id, raw.type)
      const type = checkType(id, raw.type, blockTypes)
      if (type && !canContain(parentType, type)) {
        issues.push({ code: 'invalid-nesting', id, message: `A ${type} cannot go inside a ${parentType}.` })
      }
      // A block may carry its own children under data.blocks (containers/columns).
      const nested = (raw.data as { blocks?: unknown } | undefined)?.blocks
      if (nested !== undefined) walkBlocks(nested, type ?? 'container')
    }
  }

  const sections = payload?.sections
  if (!Array.isArray(sections)) {
    issues.push({ code: 'corrupt-root', id: null, message: 'The layout has no valid sections array.' })
    return issues
  }
  for (const raw of sections as SectionNode[]) {
    if (!raw || typeof raw !== 'object') {
      issues.push({ code: 'corrupt-node', id: null, message: 'A section is not a valid object.' })
      continue
    }
    const id = checkId(raw.id, raw.type)
    const type = checkType(id, raw.type, sectionTypes)
    walkBlocks(raw.blocks, type ?? 'section')
  }
  return issues
}
