// Phase 26.11 — insert a pattern through the EXISTING command pipeline.
//
// This never mutates BuilderState, the component tree, or Vue state directly. It restores
// the snapshot to id-free sections and dispatches the existing `insert-pattern` command —
// the same one layout presets use — so the guarantees come for free:
//   • ONE command  → one undo entry
//   • server re-keys ids → no duplicate component ids
//   • server selects the new root → `executeCommand` mirrors it into the selection
//
// No new command, no CommandBus replacement, no renderer change.

import { restorePattern } from './restore'
import type { PatternDefinition } from './patternDefinition'

/** The minimal editor surface this seam needs (the real editor store satisfies it). */
export interface PatternInsertTarget {
  executeCommand(command: string, params?: Record<string, unknown>): Promise<boolean>
}

/**
 * Insert a pattern definition. Returns false (without dispatching) for an empty tree;
 * otherwise returns the command's success flag. Selection + undo are handled by the
 * command, exactly as for any other insert.
 */
export async function insertPattern(pattern: PatternDefinition, editor: PatternInsertTarget): Promise<boolean> {
  const sections = restorePattern(pattern.tree)
  if (sections.length === 0) return false
  return editor.executeCommand('insert-pattern', { sections })
}
