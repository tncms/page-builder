// Phase 26.24 — the runtime binding: (templateId, source) → definition → apply.
//
// This is the missing link between Library identity and the runtime. It RESOLVES the definition
// through the source resolver, then hands it to the EXISTING, UNCHANGED runtime `applyTemplate`.
// It adds no apply mechanism of its own: resolution → `applyTemplate` → `resolveTemplate` →
// `insert-pattern` = one command = one undo, exactly as before. Resolution throws BEFORE any
// command runs, so a miss (or an unknown source) leaves BuilderState and history untouched —
// atomic, like every other template apply.

import {
  applyTemplate,
  TemplateRuntimeError,
  type TemplateApplyTarget,
  type TemplateApplyDeps,
  type TemplateApplyResult,
} from '../runtime'
import type { TemplateDefinition } from '../contracts'
import type { TemplateDefinitionProvider } from './TemplateDefinitionSource'
import { templateSourceResolver } from './templateSources'

export interface TemplateSourceApplyDeps extends TemplateApplyDeps {
  /** Override the definition resolver (defaults to the shared `templateSourceResolver`). */
  definitionProvider?: TemplateDefinitionProvider
}

/**
 * Resolve a full definition for (templateId, source). Throws `unsupported_template_source` for an
 * unknown source and `template_definition_not_found` when a known source has no such template —
 * both explicit, never a silent fallback.
 */
export function resolveTemplateDefinition(
  templateId: string,
  source: string,
  provider: TemplateDefinitionProvider = templateSourceResolver,
): TemplateDefinition {
  const definition = provider.getDefinition(templateId, source)
  if (!definition) {
    throw new TemplateRuntimeError(
      'template_definition_not_found',
      `Template definition "${templateId}" (source "${source}") not found.`,
      { templateId, source },
    )
  }
  return definition
}

/** Resolve a template by (templateId, source) and apply it through the runtime (one undo). */
export async function applyTemplateFromSource(
  templateId: string,
  source: string,
  editor: TemplateApplyTarget,
  deps: TemplateSourceApplyDeps = {},
): Promise<TemplateApplyResult> {
  const definition = resolveTemplateDefinition(templateId, source, deps.definitionProvider) // throws → no command
  return applyTemplate(definition, editor, deps)
}
