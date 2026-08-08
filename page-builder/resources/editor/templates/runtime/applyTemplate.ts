// Phase 26.17 — the ONE canonical template apply path.
//
// Applying a template = resolve → hand the resulting PatternTree to the EXISTING pattern
// insertion pipeline. It reuses `insertPattern` verbatim, so a template application is one
// `insert-pattern` command = one CommandBus round-trip = one undo entry, with server-side
// id re-keying and existing selection behaviour. It never writes BuilderState directly,
// never bypasses the CommandBus, and never applies sections through multiple commands.
//
// Atomic: `resolveTemplate` throws BEFORE any command runs, so a resolution failure leaves
// BuilderState and history untouched (no command, no undo entry).
//
// This phase supports only the `insert` mode (insert into the current page). replace/
// merge/append/page-assignment are later phases and are intentionally absent.

import { insertPattern } from '../../patterns/library'
import type { PatternProvider } from '../../patterns/provider'
import { templateProvider as defaultTemplateProvider } from '../provider'
import type { TemplateDefinition, TemplateProvider } from '../contracts'
import { resolveTemplate, type ResolvedTemplate } from './resolveTemplate'
import { TemplateRuntimeError } from './TemplateRuntimeError'

/** The minimal editor surface apply needs (the real editor store satisfies it). */
export interface TemplateApplyTarget {
  executeCommand(command: string, params?: Record<string, unknown>): Promise<boolean>
  selectedComponentId: string | null
}

export interface TemplateApplyDeps {
  patternProvider?: PatternProvider
  templateProvider?: TemplateProvider
}

export interface TemplateApplyResult {
  ok: boolean
  templateId: string
  /** The component selected after insertion (server-selected new root). */
  selected: string | null
  resolved: ResolvedTemplate
}

/** Apply a template definition into the current page via the existing insert pipeline. */
export async function applyTemplate(
  template: TemplateDefinition,
  editor: TemplateApplyTarget,
  deps: TemplateApplyDeps = {},
): Promise<TemplateApplyResult> {
  const resolved = resolveTemplate(template, { patternProvider: deps.patternProvider }) // throws → no command

  // Reuse the pattern insert path (id-free restore → server re-keys → one undo → selection).
  const ok = await insertPattern({ id: template.id, name: template.name, tree: resolved.tree }, editor)

  return { ok, templateId: template.id, selected: editor.selectedComponentId, resolved }
}

/** Apply a REGISTERED template by id. Throws `template_not_found` when it is unknown. */
export async function applyTemplateById(
  id: string,
  editor: TemplateApplyTarget,
  deps: TemplateApplyDeps = {},
): Promise<TemplateApplyResult> {
  const provider = deps.templateProvider ?? defaultTemplateProvider
  const template = provider.get(id)
  if (!template) {
    throw new TemplateRuntimeError('template_not_found', `Template "${id}" not found.`, { id })
  }
  return applyTemplate(template, editor, deps)
}
