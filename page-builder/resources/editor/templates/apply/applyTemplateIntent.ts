// Phase 26.23 — the Picker → Runtime apply seam (intent, not mechanism).
// Phase 26.24 — now source-aware: it resolves a selection's (templateId, source) into a full
// definition before applying, so built-in / imported / user templates all apply through the same
// path.
//
// This is the ONE place a Template Picker selection turns into a runtime apply. It delegates to
// the Phase 26.24 binding `applyTemplateFromSource`, which resolves the definition and hands it to
// the UNCHANGED runtime `applyTemplate`. It never resolves a layout itself, reimplements
// `insertPattern`, dispatches a command, or writes BuilderState. Applying stays atomic: one
// resolve → one `insert-pattern` command → one undo entry (all owned by the runtime).
//
// A `TemplateLibraryItem` carries metadata only (no serialized blocks); the full definition is
// resolved by source (`template_definition_not_found` / `unsupported_template_source` surface as
// the runtime's typed errors, handled by the Apply UX).

import type { TemplateApplyTarget, TemplateApplyResult } from '../runtime'
import type { TemplateSelection } from '../picker'
import { applyTemplateFromSource, type TemplateSourceApplyDeps } from '../source'

/** Apply a Template Picker selection through the source-resolving runtime binding. */
export function applyTemplateSelection(
  selection: TemplateSelection,
  editor: TemplateApplyTarget,
  deps: TemplateSourceApplyDeps = {},
): Promise<TemplateApplyResult> {
  return applyTemplateFromSource(selection.templateId, selection.source, editor, deps)
}
